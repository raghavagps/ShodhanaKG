<?php
$file = 'src/components/explorer/ProvenancePanel.tsx';
$content = file_get_contents($file);
$content = str_replace(
    '<p className="text-xs text-muted-foreground mt-1">{error.message}</p>',
    '<p className="text-xs text-muted-foreground mt-1">{error.message}</p><p className="text-xs text-red-500">{import.meta.env.VITE_API_BASE}</p>',
    $content
);
file_put_contents($file, $content);
