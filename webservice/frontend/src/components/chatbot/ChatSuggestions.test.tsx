import { render, screen, fireEvent } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import { ChatSuggestions } from './ChatSuggestions';
it('keeps five KG starters and only two introductory cards', () => {
  const onSelect=vi.fn(); render(<ChatSuggestions onSelect={onSelect} />);
  expect(screen.getAllByRole('button')).toHaveLength(7);
  for(const name of ['Which compounds use Sesame Oil?','What is Shodhana?','What is Panchakarma?']) {
    fireEvent.click(screen.getByRole('button',{name})); expect(onSelect).toHaveBeenLastCalledWith(name);
  }
  expect(screen.queryByRole('button',{name:'What are Shodhana methods?'})).not.toBeInTheDocument();
});
