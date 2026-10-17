<?php
// Script to safely replace the logic in provenance-subgraph.php
$file = 'api/provenance-subgraph.php';
$content = file_get_contents($file);

$search2 = <<<'EOD'
      foreach ( as ) {
         = isset(['@in']) ? ['@in'] : '';
         = isset(['Pubmed ID']) ? ['Pubmed ID'] : (isset(['ref']) ? ['ref'] : null);
        
        // Normalize PMID (remove .0 suffix from floats)
         =  !== null ? rtrim(rtrim((string), '0'), '.') : null;
        
        // Filter by pubmedId if provided
        if ( !== '' &&  !== ) {
          continue;
        }
        
        if ( &&  !== null) {
          [] = array(
            'edge' => ,
            'therapyRid' => ,
            'pubmedId' => ,
            'edgeType' => ,
          );
          [] = true;
        }
      }
EOD;

$replace2 = <<<'EOD'
      foreach ( as ) {
         = isset(['@in']) ? ['@in'] : '';
         = EvidenceCorrelator::extractIdentifiers();
        
        // Filter by pubmedId if provided
        if ( !== '' && ['pubmed_id'] !== ) {
          continue;
        }
        
        if ( && (['pubmed_id'] !== '' || ['doi'] !== '' || ['ayush_id'] !== '')) {
          [] = array(
            'edge' => ,
            'therapyRid' => ,
            'props' => ,
            'edgeType' => ,
          );
          [] = true;
        }
      }
EOD;

$search3 = <<<'EOD'
      foreach ( as ) {
         = isset(['@out']) ? ['@out'] : '';
         = isset(['@in']) ? ['@in'] : '';
         = isset(['Pubmed ID']) ? ['Pubmed ID'] : (isset(['ref']) ? ['ref'] : null);
        
        // Normalize PMID (remove .0 suffix from floats)
         =  !== null ? rtrim(rtrim((string), '0'), '.') : null;
        
        // Filter by pubmedId if provided
        if ( !== '' &&  !== ) {
          continue;
        }
        
        if ( &&  &&  !== null) {
          if (!isset([])) {
            [] = array();
          }
          [][] = array(
            'edge' => ,
            'formulationRid' => ,
            'pubmedId' => ,
          );
          [] = true;
        }
      }
EOD;

$replace3 = <<<'EOD'
      foreach ( as ) {
         = isset(['@out']) ? ['@out'] : '';
         = isset(['@in']) ? ['@in'] : '';
         = EvidenceCorrelator::extractIdentifiers();
        
        // Filter by pubmedId if provided
        if ( !== '' && ['pubmed_id'] !== ) {
          continue;
        }
        
        if ( &&  && (['pubmed_id'] !== '' || ['doi'] !== '' || ['ayush_id'] !== '')) {
          if (!isset([])) {
            [] = array();
          }
          [][] = array(
            'edge' => ,
            'formulationRid' => ,
            'props' => ,
          );
          [] = true;
        }
      }
EOD;

$search5 = <<<'EOD'
  foreach ( as ) {
    if ( >= ) break;
    
     = ['therapyRid'];
     = ['pubmedId'];
    
    // Check if this therapy has outgoing formulation edges with matching PMID
    if (!isset([])) {
      continue;
    }
    
    foreach ([] as ) {
      if ( >= ) break;
      
       = ['pubmedId'];
      
      // Provenance validation: PMIDs must match
      if ( !== ) {
        continue;
      }
      
      ++;
      [] = true;
      
       = build_node([]);
       = build_node([['formulationRid']]);
      
       = map_edge_props(['edge']);
       = map_edge_props(['edge']);
      
      [] = array(
        'disease' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Disease',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge1' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => normalize_edge_type(['edgeType']),
          'pubmedId' => ,
        ),
        'therapy' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Therapy',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge2' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => 'USES_FORMULATION',
          'pubmedId' => ,
          'dose' => isset(['dose']) ? ['dose'] : null,
          'duration' => isset(['duration']) ? ['duration'] : null,
          'adjuvant' => isset(['adjuvant']) ? ['adjuvant'] : null,
        ),
        'formulation' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => ['type'],
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'provenance' => array(
          'pubmedId' => ,
          'confidence' => 'high',
        ),
      );
    }
  }
EOD;

$replace5 = <<<'EOD'
  foreach ( as ) {
    if ( >= ) break;
    
     = ['therapyRid'];
     = ['props'];
    
    // Check if this therapy has outgoing formulation edges with matching PMID
    if (!isset([])) {
      continue;
    }
    
    foreach ([] as ) {
      if ( >= ) break;
      
       = ['props'];
      
      // Provenance validation: Use EvidenceCorrelator
      if (!EvidenceCorrelator::correlate(, )) {
        continue;
      }
      
      ++;
      if (['pubmed_id'] !== '') [['pubmed_id']] = true;
      if (['pubmed_id'] !== '') [['pubmed_id']] = true;
      
       = build_node([]);
       = build_node([['formulationRid']]);
      
       = map_edge_props(['edge']);
       = map_edge_props(['edge']);
      
       = ['pubmed_id'] !== '' ? ['pubmed_id'] : (['doi'] !== '' ? ['doi'] : ['ayush_id']);
      
      [] = array(
        'disease' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Disease',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge1' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => normalize_edge_type(['edgeType']),
          'pubmedId' => ['pubmed_id'] !== '' ? ['pubmed_id'] : null,
          'doi' => ['doi'] !== '' ? ['doi'] : null,
          'ayushId' => ['ayush_id'] !== '' ? ['ayush_id'] : null,
        ),
        'therapy' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Therapy',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge2' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => 'USES_FORMULATION',
          'pubmedId' => ['pubmed_id'] !== '' ? ['pubmed_id'] : null,
          'doi' => ['doi'] !== '' ? ['doi'] : null,
          'ayushId' => ['ayush_id'] !== '' ? ['ayush_id'] : null,
          'dose' => isset(['dose']) ? ['dose'] : null,
          'duration' => isset(['duration']) ? ['duration'] : null,
          'adjuvant' => isset(['adjuvant']) ? ['adjuvant'] : null,
        ),
        'formulation' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => ['type'],
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'provenance' => array(
          'pubmedId' => ,
          'confidence' => 'high',
        ),
      );
    }
  }
EOD;

$content = str_replace($search2, $replace2, $content);
$content = str_replace($search3, $replace3, $content);
$content = str_replace($search5, $replace5, $content);

file_put_contents($file, $content);
echo "Updated provenance-subgraph.php\n";
