import re

with open("D:/ShodhanaUI/shodhanakg-explorer-suite-main - final-copy/src/pages/HowToUse.tsx", "r", encoding="utf-8") as f:
    content = f.read()

# Fix the TabsList layout
content = content.replace(
    'className="w-full grid grid-cols-2 md:grid-cols-6 h-auto p-1.5 gap-1.5 bg-muted/60 backdrop-blur-sm border border-border/40 rounded-xl"',
    'className="w-full grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 h-auto p-1.5 gap-1.5 bg-muted/60 backdrop-blur-sm border border-border/40 rounded-xl"'
)

# Replace Vertex Node Types table body (web view)
new_vertex_table = """
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Disease</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#E07A5F]/10 text-[#E07A5F]"><span className="h-2 w-2 rounded-full bg-[#E07A5F]" />Orange</span></td>
                          <td className="p-3 text-muted-foreground">Modern clinical conditions and diseases.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Symptoms</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#81B29A]/10 text-[#81B29A]"><span className="h-2 w-2 rounded-full bg-[#81B29A]" />Green</span></td>
                          <td className="p-3 text-muted-foreground">Physical manifestations or symptoms associated with a disease.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Ayurvedic Diagnosis</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#9B5DE5]/10 text-[#9B5DE5]"><span className="h-2 w-2 rounded-full bg-[#9B5DE5]" />Purple</span></td>
                          <td className="p-3 text-muted-foreground">Ayurvedic traditional terminologies for clinical states.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Doshas</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#E76F51]/10 text-[#E76F51]"><span className="h-2 w-2 rounded-full bg-[#E76F51]" />Red-Orange</span></td>
                          <td className="p-3 text-muted-foreground">Bodily humors/imbalances (Vata, Pitta, Kapha).</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Therapy</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#E9C46A]/10 text-[#E9C46A]"><span className="h-2 w-2 rounded-full bg-[#E9C46A]" />Yellow</span></td>
                          <td className="p-3 text-muted-foreground">Ayurvedic purification therapies (Panchakarma steps).</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Formulation</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#3D5A80]/10 text-[#3D5A80]"><span className="h-2 w-2 rounded-full bg-[#3D5A80]" />Teal</span></td>
                          <td className="p-3 text-muted-foreground">Traditional polyherbal recipes and medical preparations.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Assessment Parameters</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#00B4D8]/10 text-[#00B4D8]"><span className="h-2 w-2 rounded-full bg-[#00B4D8]" />Cyan</span></td>
                          <td className="p-3 text-muted-foreground">Clinical scoring scales for evaluating therapy outcomes.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Compound</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#3F37C9]/10 text-[#3F37C9]"><span className="h-2 w-2 rounded-full bg-[#3F37C9]" />Blue</span></td>
                          <td className="p-3 text-muted-foreground">Molecular chemical constituents and isolated bioactives.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Shodhana Method</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#4361EE]/10 text-[#4361EE]"><span className="h-2 w-2 rounded-full bg-[#4361EE]" />Indigo</span></td>
                          <td className="p-3 text-muted-foreground">Specific protocols used for traditional purification of compounds.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Toxic Substance</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F72585]/10 text-[#F72585]"><span className="h-2 w-2 rounded-full bg-[#F72585]" />Rose</span></td>
                          <td className="p-3 text-muted-foreground">Toxic markers or substances tracked before and after purification.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Purified Compound</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#48CAE4]/10 text-[#48CAE4]"><span className="h-2 w-2 rounded-full bg-[#48CAE4]" />Sky</span></td>
                          <td className="p-3 text-muted-foreground">The therapeutic end-product after Shodhana processing.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Shodhana Type</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#7209B7]/10 text-[#7209B7]"><span className="h-2 w-2 rounded-full bg-[#7209B7]" />Violet</span></td>
                          <td className="p-3 text-muted-foreground">Categorizations of Shodhana procedures.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Media</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#FFB703]/10 text-[#FFB703]"><span className="h-2 w-2 rounded-full bg-[#FFB703]" />Amber</span></td>
                          <td className="p-3 text-muted-foreground">Liquids/adjuvants used during processing (e.g. cow urine, herbal juices).</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Confirmation Study</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#06D6A0]/10 text-[#06D6A0]"><span className="h-2 w-2 rounded-full bg-[#06D6A0]" />Emerald</span></td>
                          <td className="p-3 text-muted-foreground">Studies proving the safety/efficacy of the purified compound.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Process</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#64748B]/10 text-[#64748B]"><span className="h-2 w-2 rounded-full bg-[#64748B]" />Slate</span></td>
                          <td className="p-3 text-muted-foreground">Individual procedural steps applied during Shodhana.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Indication</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#FF8FAB]/10 text-[#FF8FAB]"><span className="h-2 w-2 rounded-full bg-[#FF8FAB]" />Pink</span></td>
                          <td className="p-3 text-muted-foreground">Specific targeted diseases or conditions for a purified compound.</td>
                        </tr>
                        <tr>
                          <td className="p-3 font-semibold text-foreground">Ayurvedic Formulation</td>
                          <td className="p-3"><span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#D90429]/10 text-[#D90429]"><span className="h-2 w-2 rounded-full bg-[#D90429]" />Fuchsia</span></td>
                          <td className="p-3 text-muted-foreground">Final commercial or classical formulation incorporating the purified compound.</td>
                        </tr>
"""

new_edge_table = """
                        <tr><td className="p-3 font-semibold text-foreground">HAS_SYMPTOM</td><td className="p-3">Disease → Symptom</td><td className="p-3 text-muted-foreground">Connects a clinical condition to its documented physical symptoms.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">HAS_AYURVEDIC_DIAGNOSIS</td><td className="p-3">Disease → Ayurvedic Diagnosis</td><td className="p-3 text-muted-foreground">Bridges Western diagnosis names to traditional Ayurvedic nomenclature.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">HAS_DOSHA_IMBALANCE</td><td className="p-3">Disease → Doshas</td><td className="p-3 text-muted-foreground">Maps disease conditions to specific dosha imbalances.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">TREATED_BY</td><td className="p-3">Disease/Diagnosis → Therapy</td><td className="p-3 text-muted-foreground">Links clinical conditions to specific treatments.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">PRE_TREATED_BY</td><td className="p-3">Disease/Diagnosis → Therapy</td><td className="p-3 text-muted-foreground">Links to preparatory treatments.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">POST_TREATED_BY</td><td className="p-3">Disease/Diagnosis → Therapy</td><td className="p-3 text-muted-foreground">Links to post-care treatments.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">USES_FORMULATION</td><td className="p-3">Therapy → Formulation</td><td className="p-3 text-muted-foreground">Identifies medicinal formulations used in a specific therapy.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">ASSESS</td><td className="p-3">Assessment Parameters → Disease</td><td className="p-3 text-muted-foreground">Metrics used to assess treatment outcomes for a disease.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">RESOLVED_BY</td><td className="p-3">Disease → (Unused)</td><td className="p-3 text-muted-foreground">Declares resolution of disease.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">UNDERGOES</td><td className="p-3">Compound → Shodhana Method</td><td className="p-3 text-muted-foreground">Connects a raw compound to its purification methodology.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">CONTAINS</td><td className="p-3">Compound → Toxic Substance</td><td className="p-3 text-muted-foreground">Identifies harmful components within a raw compound.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">VALIDATED_BY</td><td className="p-3">Shodhana Method → Confirmation Study</td><td className="p-3 text-muted-foreground">Provides scientific validation of the method.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">USES_MEDIA</td><td className="p-3">Shodhana Method → Media</td><td className="p-3 text-muted-foreground">Catalysts or liquid media used in processing.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">CONFIRMS_SAFETY_OF</td><td className="p-3">Confirmation Study → Purified Compound</td><td className="p-3 text-muted-foreground">Validates safety and toxicity removal.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">DERIEVED_FROM</td><td className="p-3">Purified Compound → Compound</td><td className="p-3 text-muted-foreground">Traces the purified product back to its raw origin.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">DETOXIFIES</td><td className="p-3">Shodhana Method → Toxic Substance</td><td className="p-3 text-muted-foreground">Highlights what specific toxins are removed.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">CLASSIFIED_AS</td><td className="p-3">Shodhana Method → Shodhana Type</td><td className="p-3 text-muted-foreground">Classifies the category of Shodhana.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">USED_FOR_TREATMENT</td><td className="p-3">Purified Compound → Disease</td><td className="p-3 text-muted-foreground">Links compound outcomes to target diseases.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">USED_IN_FORMULATION</td><td className="p-3">Purified Compound → Ayurvedic Formulation</td><td className="p-3 text-muted-foreground">Links the processed item into final medicine.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">FOLLOWED_BY</td><td className="p-3">Shodhana Method/Process → (Next)</td><td className="p-3 text-muted-foreground">Sequential steps in purification.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">TREATS</td><td className="p-3">Purified Compound → Indication</td><td className="p-3 text-muted-foreground">Connects a compound to its general indications.</td></tr>
                        <tr><td className="p-3 font-semibold text-foreground">PRODUCES</td><td className="p-3">Shodhana Method → Purified Compound</td><td className="p-3 text-muted-foreground">The output of the Shodhana process.</td></tr>
"""

# Replace web view vertex table
content = re.sub(
    r'<tbody className="divide-y divide-border/40">.*?</tbody>',
    f'<tbody className="divide-y divide-border/40">{new_vertex_table}</tbody>',
    content,
    count=1,
    flags=re.DOTALL
)

# Replace web view edge table
content = re.sub(
    r'<tbody className="divide-y divide-border/40">.*?</tbody>',
    f'<tbody className="divide-y divide-border/40">{new_edge_table}</tbody>',
    content,
    count=1,
    flags=re.DOTALL
)

# Now replace print view tables
print_vertex_table = """
              <tr><td className="p-2 font-bold border-r border-gray-300">Disease</td><td className="p-2 border-r border-gray-300 italic text-gray-600">disease</td><td className="p-2 text-gray-700">Modern clinical conditions and diseases.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Symptoms</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">disease</td><td className="p-2 text-gray-700 bg-gray-50">Physical manifestations or symptoms associated with a disease.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Ayurvedic Diagnosis</td><td className="p-2 border-r border-gray-300 italic text-gray-600">disease</td><td className="p-2 text-gray-700">Ayurvedic traditional terminologies for clinical states.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Doshas</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">disease</td><td className="p-2 text-gray-700 bg-gray-50">Bodily humors/imbalances (Vata, Pitta, Kapha).</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Therapy</td><td className="p-2 border-r border-gray-300 italic text-gray-600">disease</td><td className="p-2 text-gray-700">Ayurvedic purification therapies (Panchakarma steps).</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Formulation</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">disease</td><td className="p-2 text-gray-700 bg-gray-50">Traditional polyherbal recipes and medical preparations.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Assessment Parameters</td><td className="p-2 border-r border-gray-300 italic text-gray-600">disease</td><td className="p-2 text-gray-700">Clinical scoring scales for evaluating therapy outcomes.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Compound</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">compound</td><td className="p-2 text-gray-700 bg-gray-50">Molecular chemical constituents and isolated bioactives.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Shodhana Method</td><td className="p-2 border-r border-gray-300 italic text-gray-600">compound</td><td className="p-2 text-gray-700">Protocols used for traditional purification of compounds.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Toxic Substance</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">compound</td><td className="p-2 text-gray-700 bg-gray-50">Toxic markers tracked before and after purification.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Purified Compound</td><td className="p-2 border-r border-gray-300 italic text-gray-600">compound</td><td className="p-2 text-gray-700">The therapeutic end-product after Shodhana processing.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Shodhana Type</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">compound</td><td className="p-2 text-gray-700 bg-gray-50">Categorizations of Shodhana procedures.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Media</td><td className="p-2 border-r border-gray-300 italic text-gray-600">compound</td><td className="p-2 text-gray-700">Liquids/adjuvants used during processing.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Confirmation Study</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">compound</td><td className="p-2 text-gray-700 bg-gray-50">Studies proving safety/efficacy of the purified compound.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Process</td><td className="p-2 border-r border-gray-300 italic text-gray-600">compound</td><td className="p-2 text-gray-700">Individual procedural steps applied during Shodhana.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">Indication</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">compound</td><td className="p-2 text-gray-700 bg-gray-50">Targeted diseases or conditions for a purified compound.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">Ayurvedic Formulation</td><td className="p-2 border-r border-gray-300 italic text-gray-600">compound</td><td className="p-2 text-gray-700">Final formulation incorporating the purified compound.</td></tr>
"""

print_edge_table = """
              <tr><td className="p-2 font-bold border-r border-gray-300">HAS_SYMPTOM</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Disease → Symptom</td><td className="p-2 text-gray-700">Connects condition to physical symptoms.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">HAS_AYURVEDIC_DIAGNOSIS</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Disease → Ayurvedic Diagnosis</td><td className="p-2 text-gray-700 bg-gray-50">Bridges to traditional nomenclature.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">HAS_DOSHA_IMBALANCE</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Disease → Doshas</td><td className="p-2 text-gray-700">Maps disease to dosha imbalances.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">TREATED_BY</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Disease/Diagnosis → Therapy</td><td className="p-2 text-gray-700 bg-gray-50">Links conditions to specific treatments.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">PRE_TREATED_BY</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Disease/Diagnosis → Therapy</td><td className="p-2 text-gray-700">Links to preparatory treatments.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">POST_TREATED_BY</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Disease/Diagnosis → Therapy</td><td className="p-2 text-gray-700 bg-gray-50">Links to post-care treatments.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">USES_FORMULATION</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Therapy → Formulation</td><td className="p-2 text-gray-700">Medicinal formulations used in therapy.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">ASSESS</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Assessment Parameters → Disease</td><td className="p-2 text-gray-700 bg-gray-50">Metrics used to assess treatment outcomes.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">RESOLVED_BY</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Disease → (Unused)</td><td className="p-2 text-gray-700">Declares resolution.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">UNDERGOES</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Compound → Shodhana Method</td><td className="p-2 text-gray-700 bg-gray-50">Connects compound to purification method.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">CONTAINS</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Compound → Toxic Substance</td><td className="p-2 text-gray-700">Identifies harmful components.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">VALIDATED_BY</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Shodhana Method → Confirmation Study</td><td className="p-2 text-gray-700 bg-gray-50">Provides scientific validation.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">USES_MEDIA</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Shodhana Method → Media</td><td className="p-2 text-gray-700">Catalysts or liquid media used.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">CONFIRMS_SAFETY_OF</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Confirmation Study → Purified Compound</td><td className="p-2 text-gray-700 bg-gray-50">Validates safety and toxicity removal.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">DERIEVED_FROM</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Purified Compound → Compound</td><td className="p-2 text-gray-700">Traces purified product back to origin.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">DETOXIFIES</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Shodhana Method → Toxic Substance</td><td className="p-2 text-gray-700 bg-gray-50">Highlights toxins removed.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">CLASSIFIED_AS</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Shodhana Method → Shodhana Type</td><td className="p-2 text-gray-700">Classifies category of Shodhana.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">USED_FOR_TREATMENT</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Purified Compound → Disease</td><td className="p-2 text-gray-700 bg-gray-50">Links compound outcomes to diseases.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">USED_IN_FORMULATION</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Purified Compound → Ayurvedic Formulation</td><td className="p-2 text-gray-700">Links processed item into medicine.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">FOLLOWED_BY</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Shodhana Method/Process → (Next)</td><td className="p-2 text-gray-700 bg-gray-50">Sequential steps in purification.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300">TREATS</td><td className="p-2 border-r border-gray-300 italic text-gray-600">Purified Compound → Indication</td><td className="p-2 text-gray-700">Connects compound to general indications.</td></tr>
              <tr><td className="p-2 font-bold border-r border-gray-300 bg-gray-50">PRODUCES</td><td className="p-2 border-r border-gray-300 italic text-gray-600 bg-gray-50">Shodhana Method → Purified Compound</td><td className="p-2 text-gray-700 bg-gray-50">Output of Shodhana process.</td></tr>
"""

content = re.sub(
    r'<tbody className="divide-y divide-gray-300">.*?</tbody>',
    f'<tbody className="divide-y divide-gray-300">\n{print_vertex_table}            </tbody>',
    content,
    count=1,
    flags=re.DOTALL
)

content = re.sub(
    r'<tbody className="divide-y divide-gray-300">.*?</tbody>',
    f'<tbody className="divide-y divide-gray-300">\n{print_edge_table}            </tbody>',
    content,
    count=1,
    flags=re.DOTALL
)

with open("D:/ShodhanaUI/shodhanakg-explorer-suite-main - final-copy/src/pages/HowToUse.tsx", "w", encoding="utf-8") as f:
    f.write(content)
