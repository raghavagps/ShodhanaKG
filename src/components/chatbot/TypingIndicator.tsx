import React from 'react';

export const TypingIndicator = () => {
  return (
    <div role="status" aria-label="Assistant is typing" className="kg-chat-dots flex items-center space-x-1 p-2">
      <div className="w-2 h-2 bg-muted-foreground/40 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
      <div className="w-2 h-2 bg-muted-foreground/40 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
      <div className="w-2 h-2 bg-muted-foreground/40 rounded-full animate-bounce"></div>
    </div>
  );
};
