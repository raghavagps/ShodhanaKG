<?php
/**
 * Example usage of provenance-subgraph.php endpoint
 * 
 * Query parameters:
 * - diseaseId (required): Disease ID or @rid (e.g., "36" or "#5:33")
 * - pubmedId (optional): Filter by specific PubMed ID
 * - limit (optional): Max paths to return (default: 50, max: 200)
 * 
 * Example URLs:
 * - /api/provenance-subgraph.php?diseaseId=36
 * - /api/provenance-subgraph.php?diseaseId=36&pubmedId=23723667
 * - /api/provenance-subgraph.php?diseaseId=36&limit=10
 */

// Example response structure:
$exampleResponse = [
    'ok' => true,
    'paths' => [
        [
            'disease' => [
                'id' => '36',
                'label' => 'Eczema',
                'type' => 'Disease',
                'description' => ''
            ],
            'edge1' => [
                'id' => '#70:0',
                'type' => 'PRETREATED_BY',
                'pubmedId' => '23723667'
            ],
            'therapy' => [
                'id' => '1155',
                'label' => 'Abhyanga',
                'type' => 'Therapy',
                'description' => 'whole body oil massage'
            ],
            'edge2' => [
                'id' => '#84:14',
                'type' => 'USES_FORMULATION',
                'pubmedId' => '23723667',
                'dose' => null,
                'duration' => '1 Day',
                'adjuvant' => null
            ],
            'formulation' => [
                'id' => '1356',
                'label' => 'Bala Taila',
                'type' => 'Formulation',
                'description' => ''
            ],
            'provenance' => [
                'pubmedId' => '23723667',
                'confidence' => 'high'
            ]
        ]
    ],
    'meta' => [
        'totalPaths' => 3,
        'uniquePubmedIds' => ['23723667', '21455456'],
        'queryMs' => 75,
        'diseaseId' => '36'
    ]
];

echo "Provenance Subgraph API Documentation\n";
echo "====================================\n\n";
echo "Endpoint: GET /api/provenance-subgraph.php\n\n";
echo "Returns: Disease → Therapy → Formulation paths\n";
echo "         where both edges share the same PubMed ID\n\n";
echo "Example path:\n";
echo "  Eczema -(PRETREATED_BY, PMID:23723667)-> Abhyanga -(USES, PMID:23723667)-> Bala Taila\n\n";
echo "This proves that the therapy and formulation were co-cited\n";
echo "in the same literature (PubMed ID 23723667).\n";
