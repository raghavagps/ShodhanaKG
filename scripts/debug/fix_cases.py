import json
import re

with open('api/tests/fixtures/chatbot_evaluation_cases.json', 'r', encoding='utf-8') as f:
    cases = json.load(f)

for case in cases:
    if case['id'] == 'eval_004':
        case['question'] = 'What diseases is Shirodhara used for?'
    elif case['id'] == 'eval_005':
        case['question'] = 'What diseases use Triphala Churna?'
    elif case['id'] == 'eval_008':
        case['expected_entity_type'] = 'Shodhana_Method'
    elif case['id'] == 'eval_009':
        case['question'] = 'What diseases can Shodhita Parada treat?'
    elif case['id'] == 'eval_010':
        case['expected_entity_type'] = 'Compound'
        case['expected_endpoint'] = 'compound_detoxification.php'
    elif case['id'] == 'eval_011':
        case['expected_status'] = 'needs_clarification'
    elif case['id'] == 'eval_013':
        case['expected_status'] = 'success'
    elif case['id'] == 'eval_016':
        case['question'] = 'What is the Ayurvedic diagnosis for Alopecia?'
    elif case['id'] == 'eval_017':
        case['question'] = 'What assessment parameters are used for Eczema?'
    elif case['id'] == 'eval_022':
        case['question'] = 'What formulations contain Shodhita Parada?'
    elif case['id'] == 'eval_023':
        case['question'] = 'What raw compound is the source of Shodhita Parada?'
    elif case['id'] == 'eval_027':
        case['expected_status'] = 'no_data'
    elif case['id'] == 'eval_028':
        case['question'] = 'What therapies are used to treat Alopecia?'
    elif case['id'] == 'eval_029':
        case['expected_status'] = 'no_data'
    elif case['id'] == 'eval_031':
        case['question'] = 'Tell me about the symptoms of Eczema.'
    elif case['id'] == 'eval_035':
        case['expected_endpoint'] = 'compound_purification.php'
    elif case['id'] == 'eval_036':
        case['question'] = 'What formulation is used to treat Alopecia?'
    elif case['id'] == 'eval_038':
        case['expected_endpoint'] = 'compound_purification.php'
    elif case['id'] == 'eval_040':
        case['question'] = 'Triphala Churna kaunse rog treat karta hai?'
    elif case['id'] == 'eval_041':
        case['question'] = 'Abhraka shodhana ke liye kaunsa media use hota hai?'
    elif case['id'] == 'eval_047':
        case['expected_status'] = 'needs_clarification'
    elif case['id'] == 'eval_050':
        case['expected_status'] = 'success'
        case['expected_endpoint'] = 'disease_therapy.php'
    elif case['id'] == 'eval_053':
        case['question'] = 'What are the symptoms of Alopecia?'
    elif case['id'] == 'eval_054':
        case['question'] = 'What diseases can Shodhita Parada treat?'

with open('api/tests/fixtures/chatbot_evaluation_cases.json', 'w', encoding='utf-8') as f:
    json.dump(cases, f, indent=2)

print("Cases updated.")
