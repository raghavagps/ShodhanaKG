import { Button } from '@/components/ui/button';
const starterQuestions = [
  'Name 5 media used for Mardana Shodhana.',
  'Which therapies are used to treat Alopecia?',
  'Which compounds use Sesame Oil?',
  'What is the shodhana method used to purify aconitum ferox?',
  'What are the Symptoms of Psoriasis?',
  'What is Shodhana?',
  'What is Panchakarma?',
];
export function ChatSuggestions({ onSelect }: { onSelect: (question: string) => void }) {
  return <section aria-label="Suggested questions" className="kg-chat-suggestions">
    <h3>Try asking</h3>
    {starterQuestions.map(question => <Button key={question} variant="outline" size="sm" className="text-xs whitespace-normal text-left h-auto py-1.5 px-3 rounded-xl" onClick={()=>onSelect(question)}>{question}</Button>)}
  </section>;
}
