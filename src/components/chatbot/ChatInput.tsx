import React, { useState, useRef } from 'react';
import { useInputFocus } from '@/hooks/useInputFocus';
import { Button } from '@/components/ui/button';
import { SendHorizontal } from 'lucide-react';

interface ChatInputProps {
  onSend: (message: string) => void;
  disabled?: boolean;
  active?: boolean;
}

export const ChatInput: React.FC<ChatInputProps> = ({ onSend, disabled, active = true }) => {
  const [input, setInput] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  useInputFocus(inputRef, active && !disabled);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (input.trim() && !disabled) {
      onSend(input.trim());
      setInput('');
    }
  };

  return (
    <form onSubmit={handleSubmit} className="kg-chat-input flex gap-2">
      <input
        aria-label="Question"
        ref={inputRef}
        type="text"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        disabled={disabled}
        placeholder={disabled ? "Assistant is typing..." : "Ask about a disease, compound or therapy"}
        className="flex-1 rounded-full border border-border/70 bg-background px-4 py-2.5 text-[13px] shadow-sm focus:border-herbal/50 focus:outline-none focus:ring-1 focus:ring-herbal/50 disabled:opacity-50 transition-colors"
      />
      <Button 
        aria-label="Send"
        type="submit" 
        size="icon" 
        disabled={!input.trim() || disabled}
        className="rounded-full h-10 w-10 shrink-0 bg-primary hover:bg-primary/90 text-primary-foreground shadow-sm transition-transform active:scale-95 disabled:active:scale-100"
      >
        <SendHorizontal className="h-4 w-4" />
      </Button>
    </form>
  );
};
