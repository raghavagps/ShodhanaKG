import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { EdgeProperties } from './EdgeProperties';

describe('Stored edge presentation', () => {
  it.each(['pre-treated', 'treated', 'post-treated'])('displays the validated %s phase verbatim', phase => {
    render(<EdgeProperties phase={phase} properties={[{ key: 'Future_Field', label: 'Future Field', value: 'edge-specific value' }]} />);
    expect(screen.getByText(phase, { exact: false })).toBeInTheDocument();
    expect(screen.getByText('Future Field:')).toBeInTheDocument();
  });
  it('preserves meaningful zero, false and nested values', () => {
    render(<EdgeProperties properties={[{ key: 'zero', label: 'Zero', value: 0 }, { key: 'flag', label: 'Flag', value: false }, { key: 'nested', label: 'Nested', value: { route: 'oral' } }]} />);
    expect(screen.getByText(/Zero:/).parentElement).toHaveTextContent('0');
    expect(screen.getByText(/Flag:/).parentElement).toHaveTextContent('false');
    expect(screen.getByText(/Nested:/).parentElement).toHaveTextContent('oral');
    expect(screen.queryByText(/Treatment phase/)).not.toBeInTheDocument();
  });
});
