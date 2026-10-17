import { render, screen, waitFor, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { useProgressiveGraph } from './useProgressiveGraph';
afterEach(()=>{cleanup();vi.unstubAllGlobals();});
function Harness(){
  const q=useProgressiveGraph('disease',['seed']);
  return <>{q.controls}<output>{q.data?.nodes.length ?? 0}/{q.data?.edges.length ?? 0}</output></>;
}
it('loads all edge pages inside a node budget and All continues to complete the graph',async()=>{
  const budgets:number[]=[];
  vi.stubGlobal('fetch',vi.fn(async (url:string)=>{
    const params=new URL(url,'http://localhost').searchParams;
    const budget=Number(params.get('budget'));budgets.push(budget);
    const next=params.get('cursor')==='next';
    const count=Math.min(budget,350);
    return {ok:true,json:async()=>({nodes:next?[]:Array.from({length:count},(_,i)=>({id:String(i)})),edges:next?[{id:'last'}]:[{id:'first'}],meta:{totalNodes:350,totalEdges:2,loadedNodes:count,loadedEdges:next?2:1,cursor:next?null:'next',withinBudget:!next,effectiveBudget:budget,truncated:count<350}})};
  }));
  const client=new QueryClient({defaultOptions:{queries:{retry:false,gcTime:0}}});
  render(<QueryClientProvider client={client}><MemoryRouter><Harness/></MemoryRouter></QueryClientProvider>);
  expect(screen.getByLabelText('Node limit')).toHaveValue('50');
  expect(screen.getAllByRole('option').map(o=>o.textContent)).toEqual(['50','100','200','All','Custom']);
  await waitFor(()=>expect(screen.getByRole('status')).toHaveTextContent('50/2'));
  fireEvent.change(screen.getByLabelText('Node limit'),{target:{value:'all'}});
  await waitFor(()=>expect(screen.getByRole('status')).toHaveTextContent('350/2'));
  expect(budgets).toEqual([50,50,100000,100000]);
  expect(screen.queryByText('Load more')).not.toBeInTheDocument();
  fireEvent.change(screen.getByLabelText('Node limit'),{target:{value:'custom'}});
  const input=screen.getByLabelText('Custom node count');
  fireEvent.change(input,{target:{value:'75'}});
  await waitFor(()=>expect(screen.getByRole('status')).toHaveTextContent('75/2'));
  const count=budgets.length;
  for (const invalid of ['', '0', '-2', '1.5', '100001']) {
    fireEvent.change(input,{target:{value:invalid}});
    expect(input).toHaveAttribute('aria-invalid','true');
  }
  expect(budgets).toHaveLength(count);
  fireEvent.change(input,{target:{value:'120'}});
  await waitFor(()=>expect(screen.getByRole('status')).toHaveTextContent('120/2'));
  expect(input).toHaveAttribute('aria-invalid','false');
  fireEvent.change(screen.getByLabelText('Node limit'),{target:{value:'100'}});
  expect(screen.queryByLabelText('Custom node count')).not.toBeInTheDocument();
  await waitFor(()=>expect(screen.getByRole('status')).toHaveTextContent('100/2'));
  fireEvent.change(screen.getByLabelText('Node limit'),{target:{value:'custom'}});
  expect(screen.getByLabelText('Custom node count')).toHaveValue(120);
});
