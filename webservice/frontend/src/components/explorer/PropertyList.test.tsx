import { render, cleanup } from '@testing-library/react';
import { afterEach, it, expect } from 'vitest';
import { PropertyList } from './PropertyList';
import { EdgeProperties } from './EdgeProperties';
afterEach(cleanup);
it('renders node and edge values with identical markup without losing structured values', () => {
  const properties = [{ key: 'zero', label: 'Zero', value: 0 }, { key: 'flag', label: 'Flag', value: false }, { key: 'data', label: 'Data', value: { amount: ['1 g','2 g'] } }];
  const node = render(<PropertyList properties={properties} />);
  const html = node.container.innerHTML;
  expect(node.container.querySelector('dl')).not.toHaveClass('bg-secondary/20', 'rounded-lg');
  expect(node.container.querySelector('dt')).toHaveClass('uppercase');
  expect(node.container.querySelector('dd')).toHaveClass('text-xs');
  expect(node.container.textContent).toContain(JSON.stringify(properties[2].value, null, 2));
  node.unmount();
  const edge = render(<EdgeProperties properties={properties} />);
  expect(edge.container.innerHTML).toBe(html);
});
