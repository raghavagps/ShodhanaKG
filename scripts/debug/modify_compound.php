<?php
// Script to safely replace the logic in compound-provenance.php
$file = 'api/compound-provenance.php';
$content = file_get_contents($file);

// Add require
if (strpos($content, 'EvidenceCorrelator') === false) {
    $content = str_replace("require_once __DIR__ . '/graph_service.php';", "require_once __DIR__ . '/graph_service.php';\nrequire_once __DIR__ . '/core/EvidenceCorrelator.php';", $content);
}

$search2 = <<<'EOD'
      foreach ( as ) {
         = isset(['@in']) ? ['@in'] : '';
         = isset(['Pubmed ID']) ? ['Pubmed ID'] : (isset(['ref']) ? ['ref'] : null);
        
         =  !== null ? rtrim(rtrim((string), '0'), '.') : null;
        
        if () {
          [] = array(
            'edge' => ,
            'methodRid' => ,
            'pubmedId' => ,
          );
          [] = true;
        }
      }
EOD;

$replace2 = <<<'EOD'
      foreach ( as ) {
         = isset(['@in']) ? ['@in'] : '';
         = EvidenceCorrelator::extractIdentifiers();
        
        if ( && (['pubmed_id'] !== '' || ['doi'] !== '' || ['ayush_id'] !== '')) {
          [] = array(
            'edge' => ,
            'methodRid' => ,
            'props' => ,
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
        
         =  !== null ? rtrim(rtrim((string), '0'), '.') : null;
        
        if ( && ) {
          if (!isset([])) {
            [] = array();
          }
          [][] = array(
            'edge' => ,
            'mediaRid' => ,
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
        
        if ( &&  && (['pubmed_id'] !== '' || ['doi'] !== '' || ['ayush_id'] !== '')) {
          if (!isset([])) {
            [] = array();
          }
          [][] = array(
            'edge' => ,
            'mediaRid' => ,
            'props' => ,
          );
          [] = true;
        }
      }
EOD;

$search5 = <<<'EOD'
  foreach ( as ) {
    if ( >= ) break;
    
     = ['methodRid'];
     = ['pubmedId'];
    
    if (!isset([])) {
      continue;
    }
    
    foreach ([] as ) {
      if ( >= ) break;
      
       = ['pubmedId'];
      
      // Provenance validation: references/PMIDs must match if both are present
      if ( !== null &&  !== null &&  !== ) {
        continue;
      }
      
      ++;
      if ( !== null) [] = true;
      if ( !== null) [] = true;
      
       = build_node([]);
       = build_node([['mediaRid']]);
      
       = map_edge_props(['edge']);
       = map_edge_props(['edge']);
      
      [] = array(
        'compound' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Compound',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge1' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => 'UNDERGOES',
          'pubmedId' => ,
          'procedure' => isset(['procedure']) ? ['procedure'] : null,
        ),
        'shodhana_method' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Shodhana_Method',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge2' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => 'USES_MEDIA',
          'pubmedId' => ,
          'procedure' => isset(['procedure']) ? ['procedure'] : null,
          'result' => isset(['result']) ? ['result'] : null,
        ),
        'media' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Media',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'provenance' => array(
          'pubmedId' =>  !== null ?  : ,
          'confidence' => ( !== null &&  !== null) ? 'high' : 'medium',
        ),
      );
    }
  }
EOD;

$replace5 = <<<'EOD'
  foreach ( as ) {
    if ( >= ) break;
    
     = ['methodRid'];
     = ['props'];
    
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
       = build_node([['mediaRid']]);
      
       = map_edge_props(['edge']);
       = map_edge_props(['edge']);
      
       = ['pubmed_id'] !== '' ? ['pubmed_id'] : (['doi'] !== '' ? ['doi'] : ['ayush_id']);
      
      [] = array(
        'compound' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Compound',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge1' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => 'UNDERGOES',
          'pubmedId' => ['pubmed_id'] !== '' ? ['pubmed_id'] : null,
          'doi' => ['doi'] !== '' ? ['doi'] : null,
          'ayushId' => ['ayush_id'] !== '' ? ['ayush_id'] : null,
          'procedure' => isset(['procedure']) ? ['procedure'] : null,
        ),
        'shodhana_method' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Shodhana_Method',
          'description' => isset(['description']) ? ['description'] : null,
        ),
        'edge2' => array(
          'id' => isset(['edge']['@rid']) ? ['edge']['@rid'] : '',
          'type' => 'USES_MEDIA',
          'pubmedId' => ['pubmed_id'] !== '' ? ['pubmed_id'] : null,
          'doi' => ['doi'] !== '' ? ['doi'] : null,
          'ayushId' => ['ayush_id'] !== '' ? ['ayush_id'] : null,
          'procedure' => isset(['procedure']) ? ['procedure'] : null,
          'result' => isset(['result']) ? ['result'] : null,
        ),
        'media' => array(
          'id' => ['id'],
          'label' => ['label'],
          'type' => 'Media',
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
echo "Updated compound-provenance.php\n";
