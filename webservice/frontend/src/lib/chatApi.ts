import { chatFaqs } from '@/data/chatFaqs';
import { ChatbotBackendResponse, ChatHistoryTurn, OrchestratorContext, ConversationContext, EntityCandidate } from "./types";

export const API_BASE = import.meta.env.VITE_API_BASE || import.meta.env.BASE_URL.replace(/\/$/, "");

export const CHAT_TIMEOUT_MS = 125000;

async function withDeadline<T>(operation: (signal: AbortSignal) => Promise<T>, timeout: number, caller?: AbortSignal): Promise<T> {
  const controller = new AbortController();
  const cancel = () => controller.abort(caller?.reason);
  if (caller?.aborted) cancel();
  caller?.addEventListener('abort', cancel, { once: true });
  const timer = setTimeout(() => controller.abort(new Error('Chatbot request timed out. Please retry.')), timeout);
  try { return await operation(controller.signal); }
  finally { clearTimeout(timer); caller?.removeEventListener('abort', cancel); }
}

export async function fetchChatResponse(question: string, history: ChatHistoryTurn[] = [], conversation_id: string = '', conversation_context?: ConversationContext, page_offset = 0, selected_entity?: EntityCandidate, signal?: AbortSignal): Promise<ChatbotBackendResponse> {
  try {
    return await withDeadline(async requestSignal => {
    const response = await fetch(`${API_BASE}/api/chatbot_response.php`, {
      signal: requestSignal,
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ faq_id: chatFaqs.find(faq => faq.question === question)?.id, question, history, conversation_id, conversation_context, page_offset, selected_entity }),
    });

    if (!response.ok) {
      return {
        question,
        answer: "Failed to connect to the ShodhanaKG backend.",
        status: "network_error"
      };
    }

    const contentType = response.headers.get("content-type") ?? "";
    if (!contentType.includes("application/json")) {
      return {
        question,
        answer: "The server timed out or is unavailable. Please try again.",
        status: "network_error"
      };
    }

    const data: ChatbotBackendResponse = await response.json();
    
    if (!data.status) {
       return {
        question,
        answer: "Received an invalid response format from the server.",
        status: "network_error"
      };
    }
    
    return data;
    }, CHAT_TIMEOUT_MS, signal);
  } catch (err: unknown) {
    return {
      question,
      answer: err instanceof Error && err.message 
        ? `Network error: ${err.message}` 
        : "A network error occurred while connecting to ShodhanaKG.",
      status: "network_error"
    };
  }
}

export async function checkChatHealth(caller?: AbortSignal): Promise<boolean> {
  try {
    return await withDeadline(async signal => {
    const res = await fetch(`${API_BASE}/api/chatbot_health.php`, { signal, cache: "no-store" });
    if (res.ok) {
      const data = await res.json();
      return data.ok === true;
    }
    return false;
    }, 5000, caller);
  } catch (e) {
    return false;
  }
}
export async function fetchChatStream(
  question: string,
  history: ChatHistoryTurn[] = [],
  conversation_id: string = '',
  onToken: (chunk: string) => void,
  onContext: (context: OrchestratorContext) => void,
  conversation_context?: ConversationContext,
  page_offset = 0,
  selected_entity?: EntityCandidate,
  signal?: AbortSignal
): Promise<ChatbotBackendResponse> {
  return withDeadline(async requestSignal => {
  const response = await fetch(`${API_BASE}/api/chatbot_response.php`, {
    signal: requestSignal,
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ faq_id: chatFaqs.find(faq => faq.question === question)?.id, question, history, conversation_id, conversation_context, page_offset, selected_entity, stream: true }),
  });
  if (!response.ok) throw new Error("Failed to connect to the ShodhanaKG backend.");
  const contentType = response.headers.get("content-type") || "";
  // Compatibility with JSON-only servers and early validation responses.
  if (contentType.includes("application/json")) {
    const result: ChatbotBackendResponse = await response.json();
    if (!result.status) throw new Error("Invalid chatbot response");
    if (result.context) onContext(result.context);
    const answer = result.answer || result.message || "";
    onToken(answer);
    return { ...result, question, answer };
  }
  if (!contentType.includes("text/event-stream")) throw new Error("Unexpected chatbot response format");
  const reader = response.body?.getReader();
  if (!reader) throw new Error("Stream not supported");
  const decoder = new TextDecoder();
  let buffer = "";
  let answer = "";
  let status: ChatbotBackendResponse["status"] = "error";
  let context: OrchestratorContext | undefined;
  let completed = false;
  let presentation: Partial<ChatbotBackendResponse> = {};
  const consume = (frame: string) => {
    let event = "message";
    const lines: string[] = [];
    for (const line of frame.split(/\r?\n/)) {
      if (line.startsWith("event:")) event = line.slice(6).trim();
      if (line.startsWith("data:")) lines.push(line.slice(5).trimStart());
    }
    if (!lines.length) return;
    const payload = JSON.parse(lines.join("\n"));
    if (event === "context" && payload) {
      // Previous deployments emitted retrieval alone.
      context = "question" in payload || "node_answer" in payload || "retrieval" in payload || "routing" in payload || "reason" in payload || payload.type === 'faq_static_response'
        ? payload : { status: payload.status || "success", question, retrieval: payload };
      onContext(context!);
    } else if (event === "message" && typeof payload.chunk === "string") {
      answer += payload.chunk;
      onToken(payload.chunk);
    } else if (event === "done") {
      if (completed) throw new Error("Duplicate final chatbot event");
      if (!payload.status) throw new Error("Missing final chatbot status");
      status = payload.status;
      if (typeof payload.answer === "string") answer = payload.answer;
      presentation = { ...payload };
      if (payload.context && typeof payload.context === 'object') context = payload.context;
      completed = true;
    }
  };
  try {
    while (true) {
      const { value, done } = await reader.read();
      buffer += done ? decoder.decode() : decoder.decode(value, { stream: true });
      let boundary: RegExpExecArray | null;
      while ((boundary = /\r?\n\r?\n/.exec(buffer))) {
        consume(buffer.slice(0, boundary.index));
        buffer = buffer.slice(boundary.index + boundary[0].length);
      }
      if (done || completed) break;
    }
    if (buffer.trim()) consume(buffer);
    if (!completed) throw new Error("Chatbot stream ended before completion");
    return { ...presentation, question, answer, status, context };
  } finally {
    // done is the protocol boundary; do not wait for HTTP EOF or cancellation acknowledgement.
    void reader.cancel().catch(() => undefined);
    reader.releaseLock();
  }
  }, CHAT_TIMEOUT_MS, signal);
}
