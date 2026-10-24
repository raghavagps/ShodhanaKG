import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { beforeEach, describe, it, expect, vi } from 'vitest';
import { BrowserRouter } from 'react-router-dom';
import { ChatbotWidget } from './ChatbotWidget';
import { fetchChatStream } from '@/lib/chatApi';
import type { ConversationContext } from '@/lib/types';
vi.mock('@/lib/chatApi', () => ({ checkChatHealth: vi.fn().mockResolvedValue(true), fetchChatStream: vi.fn() }));
window.HTMLElement.prototype.scrollTo = vi.fn();
const state: ConversationContext = { version: 1, subject: { id: '#5:24', type: 'Compound', name: 'Copper' } };

describe('Conversation state transport', () => {
  beforeEach(() => { sessionStorage.clear(); vi.mocked(fetchChatStream).mockReset(); });
  it('passes validated server context to the next turn and clears it on a new conversation', async () => {
    vi.mocked(fetchChatStream).mockResolvedValue({ question: 'What is used to purify copper?', status: 'success', answer: 'Recorded methods', conversation_context: state });
    render(<BrowserRouter><ChatbotWidget /></BrowserRouter>);
    fireEvent.click(screen.getByRole('button', { name: 'Open assistant' }));
    const input = screen.getByRole('textbox');
    await waitFor(() => expect(input).not.toBeDisabled());
    fireEvent.change(input, { target: { value: 'What is used to purify copper?' } });
    fireEvent.submit(input.closest('form'));
    await screen.findByText('Recorded methods');
    fireEvent.change(input, { target: { value: 'And which diseases is it used for?' } });
    fireEvent.submit(input.closest('form'));
    await waitFor(() => expect(fetchChatStream).toHaveBeenCalledTimes(2));
    expect(vi.mocked(fetchChatStream).mock.calls[1][5]).toEqual(state);
    await waitFor(() => expect(input).not.toBeDisabled());
    fireEvent.click(screen.getByTitle('Start new conversation'));
    fireEvent.change(input, { target: { value: 'What symptoms are associated with Psoriasis?' } });
    fireEvent.submit(input.closest('form'));
    await waitFor(() => expect(fetchChatStream).toHaveBeenCalledTimes(3));
    expect(vi.mocked(fetchChatStream).mock.calls[2][5]).toBeUndefined();
  });
});

it('cancels a pending request on reset and ignores late callbacks and answers', async () => {
  sessionStorage.clear();
  vi.mocked(fetchChatStream).mockReset();
  let finish: (value: Awaited<ReturnType<typeof fetchChatStream>>) => void;
  vi.mocked(fetchChatStream).mockImplementation(() => new Promise(resolve => { finish = resolve; }));
  render(<BrowserRouter><ChatbotWidget /></BrowserRouter>);
  fireEvent.click(screen.getByRole('button', { name: 'Open assistant' }));
  const input = screen.getByRole('textbox');
  await waitFor(() => expect(input).not.toBeDisabled());
  fireEvent.change(input, { target: { value: 'What purifies copper?' } });
  fireEvent.submit(input.closest('form'));
  await waitFor(() => expect(fetchChatStream).toHaveBeenCalledTimes(1));
  const call = vi.mocked(fetchChatStream).mock.calls[0];
  fireEvent.click(screen.getByTitle('Start new conversation'));
  expect(call[8]?.aborted).toBe(true);
  await act(async () => {
    call[3]('late streamed answer');
    finish({ question: 'old', status: 'success', answer: 'late final answer', conversation_context: state });
  });
  expect(screen.queryByText(/late .* answer/)).not.toBeInTheDocument();
  expect(input).not.toBeDisabled();
});
