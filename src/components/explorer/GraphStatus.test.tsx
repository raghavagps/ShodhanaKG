import { render, screen, cleanup } from '@testing-library/react';
import { afterEach, expect, it } from 'vitest';
import { GraphStatus } from './GraphStatus';
afterEach(cleanup);
it('shows exact counts and truncation only at a completed limit', () => {
  const props = {nodes:120,edges:456,meta:{totalNodes:182,totalEdges:757,truncated:true,withinBudget:false}};
  const {rerender} = render(<GraphStatus {...props} loading={false} error={false} />);
  expect(screen.getByRole('status')).toHaveTextContent('Nodes 120/182 · Edges 456/757 · Truncated');
  rerender(<GraphStatus {...props} loading error={false} />);
  expect(screen.getByRole('status')).not.toHaveTextContent('Truncated');
  rerender(<GraphStatus {...props} meta={{...props.meta,withinBudget:true}} loading={false} error={false} />);
  expect(screen.getByRole('status')).not.toHaveTextContent('Truncated');
  rerender(<GraphStatus {...props} loading={false} error />);
  expect(screen.getByRole('status')).toHaveTextContent('Loading failed');
  expect(screen.getByRole('status')).not.toHaveTextContent('Truncated');
});
