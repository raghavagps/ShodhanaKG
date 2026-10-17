<?php
/** Curated educational/help content, never graph evidence or model knowledge. */
class FAQMatcher {
 private static function key($text) { return strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/[?.!]+$/', '', trim($text))))); }
 public static function find($question, $id = null) {
  $catalog = json_decode(file_get_contents(__DIR__ . '/faq_catalog.json'), true);
  foreach ($catalog['entries'] as $entry) {
   if ($id !== null && $entry['id'] === $id) return $entry;
   if ($id !== null) continue;
   foreach (array_merge(array($entry['question']), $entry['aliases']) as $phrase) if (self::key($question) === self::key($phrase)) return $entry;
  }
  return null;
 }
 public static function match($question) { $entry = self::find($question); return $entry === null ? null : $entry['answer']; }
}
