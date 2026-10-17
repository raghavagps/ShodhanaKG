import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, it, expect } from 'vitest';
import { useColorblind } from './useColorblind';
import { ColorblindProvider } from '../context/ColorblindProvider';
import { nodeStyle } from '@/lib/nodeStyles';
function Probe() {
  const { getPalette, toggleColorblind } = useColorblind();
  return <button onClick={toggleColorblind}>{getPalette({ Disease: nodeStyle('Disease').fill }).Disease}</button>;
}
afterEach(() => { cleanup(); localStorage.clear(); document.documentElement.classList.remove('colorblind'); });
it('switches graph colors and restores the persisted mode', () => {
  localStorage.clear();
  const { unmount } = render(<ColorblindProvider><Probe /></ColorblindProvider>);
  expect(screen.getByRole('button')).toHaveTextContent(nodeStyle('Disease').fill);
  fireEvent.click(screen.getByRole('button'));
  expect(screen.getByRole('button')).toHaveTextContent(nodeStyle('Disease', true).fill);
  expect(localStorage.getItem('shodhana-colorblind')).toBe('true');
  unmount();
  render(<ColorblindProvider><Probe /></ColorblindProvider>);
  expect(screen.getByRole('button')).toHaveTextContent(nodeStyle('Disease', true).fill);
});
