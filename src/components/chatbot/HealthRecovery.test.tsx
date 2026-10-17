import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { beforeEach, expect, it, vi } from 'vitest';
import { ChatbotWidget } from './ChatbotWidget';
import { checkChatHealth, fetchChatStream } from '@/lib/chatApi';
vi.mock('@/lib/chatApi', () => ({ checkChatHealth: vi.fn(), fetchChatStream: vi.fn() }));
window.HTMLElement.prototype.scrollTo = vi.fn();
beforeEach(() => { sessionStorage.clear(); vi.mocked(checkChatHealth).mockReset(); });
it('recovers from an initial backend outage when the window regains focus', async () => {
  vi.mocked(checkChatHealth).mockResolvedValueOnce(false).mockResolvedValue(true);
  render(<BrowserRouter><ChatbotWidget /></BrowserRouter>);
  fireEvent.click(screen.getByRole('button', { name: 'Open assistant' }));
  await screen.findByText('(Offline)');
  expect(screen.getByRole('textbox')).not.toBeDisabled();
  fireEvent(window, new Event('focus'));
  await waitFor(() => expect(screen.getByRole('textbox')).not.toBeDisabled());
  expect(screen.queryByText('(Offline)')).not.toBeInTheDocument();
});

it('permits a real request despite a failed health probe and clears stale offline state',async()=>{
 vi.mocked(checkChatHealth).mockResolvedValue(false);
 vi.mocked(fetchChatStream).mockResolvedValue({question:'What is Copper?',answer:'Stored fields',status:'success'});
 render(<BrowserRouter><ChatbotWidget /></BrowserRouter>);
 fireEvent.click(screen.getByRole('button',{name:'Open assistant'}));
 await screen.findByText('(Offline)');
 const input=screen.getByRole('textbox');fireEvent.change(input,{target:{value:'What is Copper?'}});fireEvent.submit(input.closest('form'));
 await screen.findByText('Stored fields');expect(fetchChatStream).toHaveBeenCalled();
 expect(screen.queryByText('(Offline)')).not.toBeInTheDocument();expect(input).not.toBeDisabled();
});

it('ignores a late failed health probe after a successful conversation request',async()=>{
 let finish!: (value:boolean)=>void;
 vi.mocked(checkChatHealth).mockImplementation(()=>new Promise(resolve=>{finish=resolve;}));
 vi.mocked(fetchChatStream).mockResolvedValue({question:'What is Copper?',answer:'Current response',status:'success'});
 render(<BrowserRouter><ChatbotWidget /></BrowserRouter>);
 fireEvent.click(screen.getByRole('button',{name:'Open assistant'}));
 const input=screen.getByRole('textbox');fireEvent.change(input,{target:{value:'What is Copper?'}});fireEvent.submit(input.closest('form'));
 await screen.findByText('Current response');finish(false);
 await waitFor(()=>expect(input).toBeEnabled());expect(screen.queryByText('(Offline)')).not.toBeInTheDocument();
});
