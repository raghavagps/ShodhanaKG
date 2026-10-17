import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, expect, it, vi } from 'vitest';
import NotFound from './NotFound';

afterEach(() => vi.unstubAllEnvs());

it('returns to the application base when deployed in a subdirectory', () => {
  vi.stubEnv('BASE_URL', '/raghava/shodhanakg/');
  render(<MemoryRouter initialEntries={['/missing']}><NotFound /></MemoryRouter>);
  expect(screen.getByRole('link')).toHaveAttribute('href', '/raghava/shodhanakg/');
});
