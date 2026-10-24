import { fireEvent, render, screen, within } from '@testing-library/react';
import { expect, it } from 'vitest';
import sample from '../../../../api/tests/fixtures/results_first_psoriasis.json';
import type { ChatMessage as Message } from '@/lib/types';
import { ChatMessage } from './ChatMessage';
import { EvidenceRecords } from './EvidenceRecords';

it('opens result-bound evidence without renumbering its original records', () => {
  const message = {...sample,role:'bot',text:sample.answer} as unknown as Message;
  const item = message.answer_results![0];
  const before = JSON.stringify(message);
  render(<ChatMessage message={message} />);
  const row = screen.getByRole('button',{name:`Evidence for ${item.label}`});
  expect(within(row).getByText(`${item.citation_ids.length} ${item.citation_ids.length===1?'source':'sources'}`)).toBeInTheDocument();
  fireEvent.click(row);
  const sheet=screen.getByRole('dialog',{name:'Evidence'});
  expect(within(sheet).getAllByRole('article').map(node=>node.getAttribute('data-record-index'))).toEqual(item.record_indices.slice(0,10).map(String));
  fireEvent.click(within(sheet).getByRole('button',{name:'AYUSH'}));
  const expected=item.record_indices.filter(index=>message.citations!.some(c=>c.record_indices.includes(index)&&c.scopes.some(s=>s.identifiers.references?.some(r=>r.namespace==='AYUSH'&&['verified','declared'].includes(r.status)) || (!s.identifiers.references?.length&&s.identifiers.ayush_id))));
  expect(within(sheet).queryAllByRole('article')).toHaveLength(Math.min(expected.length,10));
  fireEvent.keyDown(sheet,{key:'Escape'});
  expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  expect(JSON.stringify(message)).toBe(before);
});

it('keeps original record identity through filtered evidence pagination',()=>{
  const records=Array.from({length:40},(_,i)=>({media:`Media ${i+1}`}));
  const indices=Array.from({length:15},(_,i)=>i*2+1);
  render(<EvidenceRecords records={records} visibleRecordIndices={indices} />);
  expect(screen.getAllByRole('article')).toHaveLength(10);
  fireEvent.click(screen.getByRole('button',{name:'Next records'}));
  expect(screen.getByRole('article',{name:'Evidence record 22'})).toBeInTheDocument();
  expect(screen.getAllByRole('article')).toHaveLength(5);
  fireEvent.click(screen.getByRole('button',{name:'Previous records'}));
  expect(screen.getByRole('article',{name:'Evidence record 2'})).toBeInTheDocument();
});


it('renders one procedure and only populated entity fields without changing source data',()=>{
  const message=structuredClone({...sample,role:'bot',text:sample.answer}) as unknown as Message;
  const scope=message.citations![0].scopes[0];
  scope.entities=[{type:'Shodhana Method',label:'Swedana'},{type:'Media',label:'Nirgundi-Turmeric Decoction'},{type:'Compound',label:''}];
  scope.relationship='Shodhana Method \u2192 Media';
  scope.properties={Procedure:'Long stored procedure',private_measurement:'Retained only in data'};
  scope.display_properties=undefined;
  const before=JSON.stringify(message);
  render(<EvidenceRecords records={message.context!.retrieval!.results} citations={message.citations} visibleRecordIndices={[0]} />);
  const card=screen.getByRole('article');
  expect(within(card).getAllByText('Long stored procedure')).toHaveLength(1);
  expect(within(card).getByText('Media:')).toBeVisible();
  expect(within(card).queryByText('Compound:')).not.toBeInTheDocument();
  expect(within(card).getByText('Relationship:')).toBeVisible();
  expect(card.querySelector('details,pre')).toBeNull();
  expect(within(card).getByText('Retained only in data')).toBeVisible();
  expect(JSON.stringify(message)).toBe(before);
});
