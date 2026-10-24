import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { ChatbotWidget } from './ChatbotWidget';
import { ChatMessage } from './ChatMessage';
import { BrowserRouter } from 'react-router-dom';
import { ChatStatus, OrchestratorContext } from '@/lib/types';

// Mock the chatApi
vi.mock('@/lib/chatApi', () => ({
  checkChatHealth: vi.fn().mockResolvedValue(true),
  fetchChatResponse: vi.fn().mockResolvedValue({
    question: "test",
    answer: "test answer",
    status: "success",
    context: {}
  }),
}));

// jsdom doesn't support scrollTo
window.HTMLElement.prototype.scrollTo = vi.fn();

describe('ChatbotWidget', () => {
  it('renders welcome message initially', async () => {
    await act(async () => {
      render(
        <BrowserRouter>
          <ChatbotWidget />
        </BrowserRouter>
      );
    });
    
    // Open the widget
    const button = screen.getByRole('button', { name: /open assistant/i });
    fireEvent.click(button);
    
    expect(screen.getByText(/Hello. I'm the ShodhanaKG Assistant/i)).toBeInTheDocument();
  });
});

describe('ChatMessage', () => {
  it('renders markdown safely', () => {
    const msg = { role: 'bot' as const, text: 'This is **bold** text and <script>alert(1)</script>' };
    render(<ChatMessage message={msg} />);
    
    const boldText = screen.getByText('bold');
    expect(boldText.tagName.toLowerCase()).toBe('strong');
    
    // Check escaping
    expect(screen.getByText((content) => content.includes('<script>alert(1)</script>'))).toBeInTheDocument();
  });

  it('keeps legacy inline references unclassified until refreshed', () => {
    const msg = { role: 'bot' as const, text: 'Here is evidence [PubMed: 123456] and [PMID: 654321]' };
    render(<ChatMessage message={msg} />);
    
    const badge1 = screen.getByText('Reference: 123456');
    const badge2 = screen.getByText('Reference: 654321');
    expect(badge1).toBeInTheDocument();
    expect(badge2).toBeInTheDocument();
    expect(badge1.closest('a')).toBeNull();
  });
  
  it('renders Evidence panel when context is provided with success status', () => {
    const contextMock: OrchestratorContext = {
      status: 'success',
      question: 'Test',
      routing: {
        domain: 'disease',
        intent: 'disease_therapy',
        entity: 'Diabetes',
        entity_type: 'Disease',
        endpoint: '/api/disease_therapy.php',
        confidence: 1.0,
        needs_clarification: false
      },
      retrieval: {
        status: 'success',
        path: ['Disease', 'TREATED_BY', 'Therapy'],
        results: [
          { name: 'Insulin', properties: { dosage: '10mg' } }
        ]
      }
    };

    const msg = { 
      role: 'bot' as const, 
      text: 'Hello',
      status: 'success' as ChatStatus,
      context: contextMock
    };
    
    render(<ChatMessage message={msg} />);
    
    const sourceBtn = screen.getByText('Evidence / Retrieved Knowledge');
    expect(sourceBtn).toBeInTheDocument();
    
    fireEvent.click(sourceBtn);
    expect(screen.getByText('Diabetes')).toBeInTheDocument();
    expect(screen.queryByText('disease_therapy')).not.toBeInTheDocument();
    expect(screen.getByRole('article', { name: 'Evidence record 1' })).toBeInTheDocument();
    expect(screen.getByText('Insulin')).toBeInTheDocument();
    expect(screen.getByText('10mg')).toBeInTheDocument();
  });

  it('renders network error explicitly', () => {
    const msg = { 
      role: 'bot' as const, 
      text: 'Failed to connect to the ShodhanaKG backend.',
      status: 'network_error' as ChatStatus
    };
    
    render(<ChatMessage message={msg} onRetry={() => {}} />);
    
    expect(screen.getByText('Failed to connect to the ShodhanaKG backend.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /retry/i })).toBeInTheDocument();
  });

  const backendStates: { status: ChatStatus, text: string }[] = [
    { status: 'no_data', text: 'I found the entity, but there is no specific data for your request.' },
    { status: 'needs_clarification', text: 'Could you please clarify your question?' },
    { status: 'unsupported', text: 'I cannot answer this type of question.' },
    { status: 'entity_not_found', text: 'I could not find the requested entity in the knowledge graph.' },
    { status: 'llm_error', text: 'The AI model is currently unavailable.' },
    { status: 'grounding_error', text: 'The generated response failed validation against the knowledge graph.' },
  ];

  backendStates.forEach(({ status, text }) => {
    it(`renders ${status} state safely without raw PHP errors or leaking context`, () => {
      const msg = { 
        role: 'bot' as const, 
        text: text,
        status: status,
        context: { status, question: 'Test question', someInternalData: 'should_not_leak' } satisfies OrchestratorContext & { someInternalData: string }
      };
      
      render(<ChatMessage message={msg} />);
      
      // 1. Deterministic message is displayed
      expect(screen.getByText(text)).toBeInTheDocument();
      
      // 2. Correct UI branch (not a network error box)
      expect(screen.queryByRole('button', { name: /retry/i })).not.toBeInTheDocument();
      
      // 3. No raw PHP errors or stack traces are displayed
      expect(screen.queryByText(/Fatal error/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/Stack trace/i)).not.toBeInTheDocument();
      
      // 4. Context is only used as evidence data (so it should NOT render Evidence panel for non-success states)
      expect(screen.queryByText('Evidence / Retrieved Knowledge')).not.toBeInTheDocument();
      
      // Ensure the internal context data hasn't leaked into the text
      expect(screen.queryByText('should_not_leak')).not.toBeInTheDocument();
    });
  });
});
