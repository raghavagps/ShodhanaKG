<?php

/**
 * KRE Pipeline Debug Logger
 */

class KRE_DebugLogger {
    private $log = [];
    private $enabled = false;
    private $timers = [];

    public function __construct($enabled = false) {
        $this->enabled = $enabled;
        $this->log = [
            'request_id'   => uniqid('kre_', true),
            'timestamp'    => date('c'),
            'user_message' => '',
            'pipeline'     => [],
            'totals'       => [
                'retrieval_ms' => 0.0,
                'llm_ms'       => 0.0,
                'total_ms'     => 0.0,
                'confidence'   => 0.0
            ]
        ];
    }

    public function isEnabled() {
        return $this->enabled;
    }

    public function setUserMessage($message) {
        if (!$this->enabled) return;
        $this->log['user_message'] = $message;
    }

    public function startTimer($stage) {
        if (!$this->enabled) return;
        $this->timers[$stage] = microtime(true);
    }

    public function endTimer($stage) {
        if (!$this->enabled || !isset($this->timers[$stage])) return 0;
        $elapsed = (microtime(true) - $this->timers[$stage]) * 1000;
        if (!isset($this->log['pipeline'][$stage])) {
            $this->log['pipeline'][$stage] = [];
        }
        $this->log['pipeline'][$stage]['ms'] = $elapsed;
        return $elapsed;
    }

    public function logStage($stage, $data) {
        if (!$this->enabled) return;
        if (!isset($this->log['pipeline'][$stage])) {
            $this->log['pipeline'][$stage] = [];
        }
        $this->log['pipeline'][$stage] = array_merge($this->log['pipeline'][$stage], $data);
    }

    public function appendToStageArray($stage, $key, $value) {
        if (!$this->enabled) return;
        if (!isset($this->log['pipeline'][$stage])) {
            $this->log['pipeline'][$stage] = [];
        }
        if (!isset($this->log['pipeline'][$stage][$key])) {
            $this->log['pipeline'][$stage][$key] = [];
        }
        $this->log['pipeline'][$stage][$key][] = $value;
    }

    public function logTotal($key, $value) {
        if (!$this->enabled) return;
        $this->log['totals'][$key] = $value;
    }

    public function getLog() {
        return $this->log;
    }

    public function saveToFile($dir = null) {
        if (!$this->enabled) return;
        if ($dir === null) {
            $dir = __DIR__ . '/../../storage/debug';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . $this->log['request_id'] . '.json';
        @file_put_contents($file, json_encode($this->log, JSON_PRETTY_PRINT));
    }
}
