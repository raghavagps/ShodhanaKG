"""Independent schema table coverage and strict How to Use scope guard."""
import json,re,unittest,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3]
AUDIT=ROOT/'webservice/api/tests/fixtures/ui_result_contract_2026_09_25_v1'
class SchemaReferenceTest(unittest.TestCase):
    def test_schema_matches_raw_records(self):
        tables=json.loads((AUDIT/'baseline/snapshot.json').read_bytes())['tables']
        records={r['@rid']:r for rows in tables.values() for r in rows}
        expected={k:{(records[e['@out']]['@type'],records[e['@in']]['@type']) for e in rows} for k,rows in tables.items() if rows and rows[0]['@cat']=='e'}
        actual=json.loads((ROOT/'webservice/frontend/src/data/schemaReference.json').read_bytes())
        self.assertEqual({n['type'] for n in actual['vertices']},{k for k,rows in tables.items() if rows and rows[0]['@cat']=='v'})
        self.assertEqual({e['type']:{(p['source'],p['target']) for p in e['pairs']} for e in actual['edges'] if e['pairs']},expected)
        self.assertEqual([e['type'] for e in actual['edges'] if not e['pairs']],[])
    def test_actual_properties(self):
        tables=json.loads((AUDIT/'baseline/snapshot.json').read_bytes())['tables']
        actual=json.loads((ROOT/'webservice/frontend/src/data/schemaReference.json').read_bytes())
        self.assertEqual(len(actual['edges']),21)
        for edge in actual['edges']:
            expected={k for r in tables[edge['type']] for k,v in r.items() if not k.startswith('@') and k not in ('out','in') and v is not None and v!='' and v!=[] and v!={}}
            self.assertEqual(set(edge['properties']),expected)
    def test_non_schema_content_is_unchanged(self):
        with zipfile.ZipFile(AUDIT/'source_before.zip') as archive:
            entry=next(n for n in archive.namelist() if n.replace('\\','/').endswith('src/pages/HowToUse.tsx'))
            before=archive.read(entry).decode('utf-8').replace('\r\n','\n')
        after=(ROOT/'webservice/frontend/src/pages/HowToUse.tsx').read_text(encoding='utf-8')
        def outside_schema(text):
            # Approved October additions: one Advanced Search tab and its hotspot
            # definition. Continue guarding every other guide against drift.
            text=re.sub(r'  const advancedSearchHotspots: Hotspot\[\] = \[.*?\n  \];\n\n', '', text, flags=re.S)
            text=re.sub(r'\s*<TabsTrigger value="advanced-search".*?</TabsTrigger>', '', text, flags=re.S)
            text=re.sub(r'\s*<TabsContent value="advanced-search".*?</TabsContent>', '', text, flags=re.S)
            text=text.replace('description: React.ReactNode;', 'description: string;').replace('lg:grid-cols-6', 'lg:grid-cols-5')
            text=re.sub(r"^import \{ SchemaVertexRows, SchemaEdgeRows \}[^\n]+\n",'',text)
            a=text.index('<TabsContent value="kg-schema"');b=text.index('</TabsContent>',a)+len('</TabsContent>')
            text=text[:a]+'SCHEMA'+text[b:]
            a=text.index('{/* Section 5 */}');b=text.index('\n        </div>',a)+len('\n        </div>')
            return re.sub(r'\n\s*\n', '\n', text[:a]+'PRINT_SCHEMA'+text[b:])
        # Explicitly approved later change: only the diagnosis swatch and its color label.
        before = before.replace('bg-[#78350f]', 'bg-[var(--node-AyurvedicDiagnosis,#7851A9)]', 1).replace(
            'Ayurvedic Diagnosis (Dark Brown):', 'Ayurvedic Diagnosis (Royal Purple):')
        self.assertEqual(outside_schema(before),outside_schema(after))
if __name__=='__main__':unittest.main()
