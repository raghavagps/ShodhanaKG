import { useState, useRef, useEffect } from "react";
import { MessageCircle, X, RefreshCw, Maximize2, Minimize2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { ChatMessage as ChatMessageType, ChatHistoryTurn } from "@/lib/types";
import { fetchChatStream, checkChatHealth } from "@/lib/chatApi";
import { ChatMessage } from "./ChatMessage";
import { ChatInput } from "./ChatInput";
import { ChatSuggestions } from "./ChatSuggestions";
import { ChatPresentation } from "./ChatPresentation";
import "./chatbot.css";

const WELCOME: ChatMessageType = {
  role: "bot",
  text: "Hello. I'm the ShodhanaKG Assistant. Ask me about diseases, compounds, therapies, or how to navigate the graph.",
};

export const ChatbotWidget = () => {
  const [open, setOpen] = useState(false);
  const [wide, setWide] = useState(false);
  const [sheetHost, setSheetHost] = useState<HTMLElement | null>(null);
  const [sheetOwner, setSheetOwner] = useState<string | null>(null);
  const launcherRef = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    if (!open) { setSheetOwner(null); return; }
  }, [open]);
  useEffect(() => {
    const navbar = document.querySelector('header.sticky');
    if (!navbar || !sheetHost) return;
    const measure = () => sheetHost.style.setProperty('--chat-top', `${navbar.getBoundingClientRect().height}px`);
    measure();
    if (typeof ResizeObserver === 'undefined') return;
    const observer = new ResizeObserver(measure); observer.observe(navbar);
    return () => observer.disconnect();
  }, [sheetHost]);
  const closePanel = () => { setSheetOwner(null); setOpen(false); requestAnimationFrame(() => launcherRef.current?.focus()); };
  useEffect(() => {
    if (!open) return;
    const escape = (event: KeyboardEvent) => {
      if (event.key !== 'Escape' || event.defaultPrevented) return;
      event.preventDefault();
      if (sheetOwner) setSheetOwner(null);
      else { setOpen(false); requestAnimationFrame(() => launcherRef.current?.focus()); }
    };
    document.addEventListener('keydown', escape);
    return () => document.removeEventListener('keydown', escape);
  }, [open, sheetOwner]);
  const [greeting, setGreeting] = useState(false);
  useEffect(() => {
    try { if (sessionStorage.getItem('chatbot-greeting-v1')) return; } catch { return; }
    const show = setTimeout(() => {
      try { sessionStorage.setItem('chatbot-greeting-v1', 'true'); } catch { return; }
      setGreeting(true);
    }, 1000);
    const hide = setTimeout(() => setGreeting(false), 8000);
    return () => { clearTimeout(show); clearTimeout(hide); };
  }, []);
  const [announce, setAnnounce] = useState(false);
  useEffect(() => {
    try {
      if (sessionStorage.getItem('chatbot-launcher-noticed')) return;
      sessionStorage.setItem('chatbot-launcher-noticed', 'true');
      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) setAnnounce(true);
    } catch { return; }
    const timer = setTimeout(() => setAnnounce(false), 700);
    return () => clearTimeout(timer);
  }, []);
  const [messages, setMessages] = useState<ChatMessageType[]>([WELCOME]);
  const [isStreaming, setIsStreaming] = useState(false);
  const [chatAvailable, setChatAvailable] = useState<boolean | null>(null);
  
  // Use a ref for conversation ID so it persists across renders without triggering effects
  const conversationIdRef = useRef(crypto.randomUUID());
  const healthVersion = useRef(0);
  const activeRequest = useRef<AbortController | null>(null);
  useEffect(() => () => activeRequest.current?.abort(), []);
  const scrollRef = useRef<HTMLDivElement>(null);

  // Recover after a transient outage without requiring a page reload.
  useEffect(() => {
    let disposed = false;
    let checking = false;
    let timer: ReturnType<typeof setTimeout>;
    const controller = new AbortController();
    const refresh = async () => {
      if (disposed || checking) return;
      clearTimeout(timer);
      if (activeRequest.current) { timer=setTimeout(refresh,5000); return; }
      checking = true;
      const version = healthVersion.current;
      let available = false;
      try {
        available = await checkChatHealth(controller.signal);
        if (!disposed && !activeRequest.current && version === healthVersion.current) setChatAvailable(available);
      } finally {
        checking = false;
        if (!disposed) timer=setTimeout(refresh, available ? 30000 : 5000);
      }
    };
    void refresh();
    window.addEventListener('online', refresh);
    window.addEventListener('focus', refresh);
    return () => {
      disposed = true; controller.abort(); clearTimeout(timer);
      window.removeEventListener('online', refresh);
      window.removeEventListener('focus', refresh);
    };
  }, []);

  // Persistence is optional: quota/privacy failures must not interrupt chatting.
  useEffect(() => {
    try {
      const saved = JSON.parse(sessionStorage.getItem('chatbot-messages') || 'null');
      if (Array.isArray(saved) && saved.length && saved.every(m => m && ['user', 'bot'].includes(m.role) && typeof m.text === 'string')) {
        setMessages(saved.slice(-40).map(m => ({ ...m, isStreaming: false })));
      }
    } catch { /* Keep the welcome state on invalid/unavailable storage. */ }
  }, []);

  useEffect(() => {
    if (messages.length <= 1) return;
    try { sessionStorage.setItem('chatbot-messages', JSON.stringify(messages.slice(-40))); }
    catch { /* Current in-memory conversation remains available. */ }
  }, [messages]);

  // Auto-scroll
  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" });
  }, [messages, open]);

  const send = async (text: string, pageOffset = 0, pageContext?: ChatMessageType['conversation_context'], selectedEntity?: import('@/lib/types').EntityCandidate) => {
    if (activeRequest.current || isStreaming) return;
    // Health is advisory: a stale probe must never prevent a fresh real request.
    healthVersion.current++;

    setMessages((m) => [
      ...m,
      { role: "user", text },
      { role: "bot", text: "", isStreaming: true }
    ]);
    setIsStreaming(true);
    const controller = new AbortController();
    activeRequest.current = controller;

    try {
      const history: ChatHistoryTurn[] = messages
        .filter(m => m.role === "user" || (m.role === "bot" && ['success', 'needs_clarification'].includes(m.status)))
        .slice(-20)
        .map(m => ({ role: m.role as "user" | "bot", text: m.text }));

      let currentAnswer = "";
      
      const response = await fetchChatStream(
        text, 
        history,
        conversationIdRef.current,
        (chunk) => {
          if (controller.signal.aborted) return;
          currentAnswer += chunk;
          setMessages((prev) => {
            const updated = [...prev];
            const last = updated[updated.length - 1];
            if (last && last.role === 'bot') {
              last.text = currentAnswer;
            }
            return updated;
          });
        },
        (context) => {
          if (controller.signal.aborted) return;
          setMessages((prev) => {
            const updated = [...prev];
            const last = updated[updated.length - 1];
            if (last && last.role === 'bot') {
              last.context = context;
            }
            return updated;
          });
        },
        pageContext ?? [...messages].reverse().find(m => m.conversation_context)?.conversation_context,
        pageOffset,
        selectedEntity,
        controller.signal
      );
      if (controller.signal.aborted) return;
      
      setMessages((prev) => {
        const updated = [...prev];
        const last = updated[updated.length - 1];
        if (last && last.role === 'bot') {
          last.isStreaming = false;
          last.text = response.answer || last.text;
          last.resolution = response.resolution;
          last.clarification = response.clarification;
          last.answer_intro = response.answer_intro;
          last.answer_heading = response.answer_heading;
          last.answer_results = response.answer_results;
          last.answer_blocks = response.answer_blocks;
          last.citations = response.citations;
          last.conversation_context = response.conversation_context;
          last.pagination = response.pagination;
          if (response.context) last.context = response.context;
          if (response.status !== 'success' && response.status !== 'no_data') {
             // Fallback text if grounding error or similar
             last.text = response.answer || "An error occurred while generating the response.";
          }
          last.status = response.status;
        }
        return updated;
      });
      setChatAvailable(!['error', 'network_error'].includes(response.status));
    } catch {
      if (controller.signal.aborted) return;
      setMessages((prev) => {
        const updated = [...prev];
        const last = updated[updated.length - 1];
        if (last && last.role === 'bot') {
          last.isStreaming = false;
          last.text = "An unexpected error occurred while communicating with the server.";
          last.status = "network_error";
        }
        return updated;
      });
      setChatAvailable(false);
    } finally {
      if (activeRequest.current === controller) {
        activeRequest.current = null;
        setIsStreaming(false);
      }
    }
  };

  const handleClear = () => {
    activeRequest.current?.abort();
    activeRequest.current = null;
    setIsStreaming(false);
    setMessages([WELCOME]);
    try { sessionStorage.removeItem('chatbot-messages'); } catch { /* optional storage */ }
    conversationIdRef.current = crypto.randomUUID();
  };

  return (
    <ChatPresentation.Provider value={{host: sheetHost, sheetOwner, setSheetOwner}}>
      {greeting && !open && <div className="fixed bottom-24 right-6 z-50 max-w-[calc(100vw-3rem)] flex items-center gap-2 rounded-xl border border-border bg-card p-3 text-sm shadow-elevated motion-safe:animate-fade-in">
        <button onClick={() => { setGreeting(false); setOpen(true); }} className="text-foreground">Hello! How can I help you?</button>
        <button aria-label="Dismiss greeting" onClick={() => setGreeting(false)} className="p-1 text-muted-foreground"><X className="h-4 w-4" /></button>
      </div>}
      {/* Floating button */}
      <button
        ref={launcherRef}
        hidden={open}
        onClick={() => { setGreeting(false); setOpen((o) => !o); }}
        className={cn(
          "kg-chat-launcher fixed bottom-6 right-6 z-50 flex items-center justify-center gap-2 rounded-full px-5 py-3",
          "bg-gradient-herbal text-primary-foreground shadow-elevated transition-smooth",
          "hover:scale-105 active:scale-95",
          announce && !open && "chat-launcher-entrance"
        )}
        aria-label={open ? "Close assistant" : "Open assistant"}
      >
        <MessageCircle className="h-5 w-5" />Ask the graph
      </button>

      {/* Presentation-only dock; request and conversation handlers are unchanged. */}
      <aside ref={setSheetHost} data-chat-open={open} data-chat-wide={wide}
        aria-label="ShodhanaKG Assistant" aria-hidden={!open}
        {...(!open ? { inert: '' } : {})}
        className="kg-chat-panel"
        onKeyDown={event => {
          if (event.key !== 'Escape') return;
          event.preventDefault(); event.stopPropagation();
          if (sheetOwner) setSheetOwner(null); else closePanel();
        }}>
        <div className="kg-chat-header">
          <h2>Ask the graph</h2>
          {chatAvailable === false && <span className="text-xs text-destructive">(Offline)</span>}
          <button className="kg-chat-icon kg-chat-widen" aria-label={wide ? 'Narrow panel' : 'Widen panel'} aria-pressed={wide} onClick={() => setWide(value => !value)}>{wide ? <Minimize2 size={17} /> : <Maximize2 size={17} />}</button>
          <button className="kg-chat-icon" aria-label="New chat" title="Start new conversation" onClick={() => {setSheetOwner(null); handleClear();}}><RefreshCw size={17} /></button>
          <button className="kg-chat-icon" aria-label="Close assistant" onClick={closePanel}><X size={18} /></button>
        </div>

        {/* Messages area */}
        <div ref={scrollRef} role="log" aria-live="polite" aria-relevant="additions text" className="kg-chat-log" {...(sheetOwner ? { inert: '' } : {})}>
          {messages.map((m, i) => (
            <ChatMessage 
              key={i} 
              message={m} 
              onSelectEntity={candidate => send(candidate.label, 0, m.conversation_context, candidate)}
              onNextPage={m.pagination?.has_more ? () => send(messages[i - 1]?.text ?? '', m.pagination.next_offset, m.conversation_context) : undefined}
              onRetry={m.status === 'network_error' ? () => {
                const userMsg = messages[i - 1]?.text;
                if (userMsg) {
                  setMessages(prev => prev.slice(0, i));
                  send(userMsg);
                }
              } : undefined}
            />
          ))}
          
          {messages.length === 1 && (
            <ChatSuggestions onSelect={send} />
          )}
        </div>

        {/* Input area */}
        <div className="kg-chat-footer" {...(sheetOwner ? { inert: '' } : {})}>
          <ChatInput onSend={send} disabled={isStreaming} active={open} />
          <p className="kg-chat-disclaimer">For academic and research use only. Not medical advice.</p>
        </div>
      </aside>
    </ChatPresentation.Provider>
  );
};




