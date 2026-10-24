"""Independent node-field oracle using the frozen raw KG snapshot, not model output."""
import re


def public_properties(node, requested='*'):
    normalize = lambda value: re.sub(r'[_\s]+', ' ', value).strip().casefold()
    return {key: value for key, value in node.items()
            if not re.match(r'^[@_]|^(in|out)(_|$)', key, re.I)
            and value is not None and value != [] and value != {}
            and not (isinstance(value, str) and not value.strip())
            and (requested == '*' or normalize(key) == normalize(requested)
                 or requested == 'description' and re.search(r'(^|[_. ])description([. ]\d+)?$', key, re.I))}


def node_issues(reference, response, records):
    node = records[reference['node_id']]
    expected = public_properties(node, reference['property'])
    evidence = response.get('node_evidence') or {}
    issues = []
    if response.get('status') != ('success' if expected else 'no_data'):
        issues.append('wrong_node_status')
    if any(evidence.get(key) != value for key, value in {
        'source': 'stored_node_properties', 'node_id': node['@rid'],
        'node_type': node['@type'], 'requested_property': reference['property'],
        'properties': expected if expected else [],
    }.items()):
        issues.append('stored_node_evidence_mismatch')
    if response.get('citations') or response.get('evidence_records') or response.get('answer_results'):
        issues.append('node_answer_invented_edge_evidence')
    if not response.get('answer', '').strip():
        issues.append('missing_node_answer')
    if not expected and 'No information is available' not in response.get('answer', ''):
        issues.append('missing_property_abstention_explanation')
    for key, value in expected.items():
        if isinstance(value, str) and (key not in response.get('answer', '') or value not in response.get('answer', '')):
            issues.append('stored_property_omitted_from_answer')
    return issues
