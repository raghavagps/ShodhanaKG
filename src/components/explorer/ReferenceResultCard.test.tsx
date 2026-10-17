import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, expect, it } from 'vitest';
import type { PublicationReference } from '@/lib/types';
import { ReferenceResultCard } from './ReferenceResultCard';

afterEach(cleanup);
const reference: PublicationReference = {raw: '12345678', identifier: '12345678', namespace: 'PMID', status: 'declared', resolution_status: 'not_checked', catalog_version: 'test'};
const records = Array.from({length: 8}, (_, i) => ({
  source: {id: 'subject', label: 'Subject', type: 'Disease'},
  target: {id: `target-${i}`, label: `Target ${i}`, type: 'Therapy'},
  edge: {id: `edge-${i}`, source: 'subject', target: `target-${i}`, type: i === 7 ? 'PRE_TREATED_BY' : 'TREATED_BY',
    display_properties: [{key: 'Dose', label: 'Dose', value: `Stored value ${i}`}], properties: {Dose: `Stored value ${i}`}},
}));
function open(details: HTMLDetailsElement, value = true) {
  details.open = value;
  fireEvent(details, new Event('toggle'));
}

it('defers unrequested rows and panels while preserving all edge counts and reference links', () => {
  const {container} = render(<MemoryRouter><ReferenceResultCard group={{id: 'reference', reference, records}} /></MemoryRouter>);
  expect(container.querySelectorAll('.reference-edge-row')).toHaveLength(5);
  expect(container.querySelectorAll('.reference-expanded')).toHaveLength(0);
  expect(container.querySelectorAll('pre')).toHaveLength(0);
  expect(screen.getByText('8 supporting edges')).toBeVisible();
  expect(screen.getByRole('link', {name: 'Open ID: PMID 12345678'})).toHaveAttribute('href', 'https://pubmed.ncbi.nlm.nih.gov/12345678/');
  fireEvent.click(screen.getByRole('button', {name: 'Show all 8 edges'}));
  expect(container.querySelectorAll('.reference-edge-row')).toHaveLength(8);
  expect(container.querySelectorAll('.reference-expanded')).toHaveLength(0);
  const last = screen.getByText('Target 7').closest('details')!;
  open(last);
  expect(within(last).getByText('Stored value 7')).toBeVisible();
  expect(within(last).getByRole('link', {name: 'Open Target 7 in Explorer'})).toHaveAttribute('href', expect.stringContaining('target-7'));
  expect(last.querySelector('pre')).toBeNull();
  const technical = within(last).getByText('Technical details').closest('details')!;
  open(technical);
  expect(JSON.parse(technical.querySelector('pre')!.textContent!)).toEqual(records[7].edge);
});

it('retains expanded properties and nested technical state when collapsing and showing fewer rows', () => {
  render(<MemoryRouter><ReferenceResultCard group={{id: 'reference', reference, records}} /></MemoryRouter>);
  fireEvent.click(screen.getByRole('button', {name: 'Show all 8 edges'}));
  const row = screen.getByText('Target 5').closest('details')!;
  open(row);
  const technical = within(row).getByText('Technical details').closest('details')!;
  open(technical);
  open(row, false);open(row);
  expect(technical.open).toBe(true);
  fireEvent.click(screen.getByRole('button', {name: 'Show fewer edges'}));
  expect(row.hidden).toBe(true);
  fireEvent.click(screen.getByRole('button', {name: 'Show all 8 edges'}));
  expect(row.hidden).toBe(false);
  expect(row.open).toBe(true);
  expect(technical.open).toBe(true);
  expect(within(row).getByText('Stored value 5')).toBeVisible();
});
