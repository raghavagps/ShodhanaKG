import { expect, type Page } from '@playwright/test';

/** Read the terminal response consumed and persisted by the real browser client.
 * Chromium does not reliably retain streamed bodies for Network.getResponseBody.
 * This keeps SSE and its production parser in the test, without a second request.
 */
export async function completedChatResponse(page: Page) {
  const read = () => page.evaluate(() => {
    const messages = JSON.parse(sessionStorage.getItem('chatbot-messages') ?? '[]');
    return messages.filter((message: {role: string}) => message.role === 'bot').at(-1);
  });
  await expect.poll(async () => {
    const message = await read();
    return !!message?.status && !message.isStreaming;
  }, { timeout: 30000, message: 'Chat must persist a terminal response' }).toBe(true);
  return read();
}
