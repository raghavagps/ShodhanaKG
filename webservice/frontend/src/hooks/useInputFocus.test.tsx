import {useRef} from 'react';
import {render, cleanup, act} from '@testing-library/react';
import {afterEach, beforeEach, expect, it, vi} from 'vitest';
import {useInputFocus} from './useInputFocus';

function Input({enabled=true}:{enabled?:boolean}) {
  const ref=useRef<HTMLInputElement>(null);useInputFocus(ref,enabled);
  return <><input ref={ref} aria-label="input"/><button>Other</button></>;
}
beforeEach(()=>{
  vi.useFakeTimers();vi.spyOn(document,'hasFocus').mockReturnValue(true);
  vi.spyOn(HTMLElement.prototype,'getClientRects').mockReturnValue([{width:100,height:20}] as unknown as DOMRectList);
});
afterEach(()=>{cleanup();vi.restoreAllMocks();vi.useRealTimers();});
it('waits through inert state then focuses once without stealing subsequent focus',()=>{
  const {getByRole}=render(<Input/>);const input=getByRole('textbox');input.setAttribute('inert','');
  act(()=>vi.advanceTimersByTime(650));expect(input).not.toHaveFocus();
  input.removeAttribute('inert');act(()=>vi.advanceTimersByTime(32));expect(input).toHaveFocus();
  getByRole('button').focus();act(()=>vi.advanceTimersByTime(1000));expect(getByRole('button')).toHaveFocus();
});
it('cancels pending focus when disabled or unmounted',()=>{
  const {getByRole,rerender,unmount}=render(<Input/>);const input=getByRole('textbox');input.setAttribute('inert','');
  act(()=>vi.advanceTimersByTime(50));rerender(<Input enabled={false}/>);input.removeAttribute('inert');
  act(()=>vi.advanceTimersByTime(1000));expect(input).not.toHaveFocus();unmount();expect(vi.getTimerCount()).toBe(0);
});
