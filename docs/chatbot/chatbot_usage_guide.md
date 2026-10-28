# Chatbot User Guide

## Table of Contents

1. [What the Chatbot Does](#1-what-the-chatbot-does)
2. [Opening and Using the Chatbot](#2-opening-and-using-the-chatbot)
3. [Example Questions](#3-example-questions)
4. [Reading an Answer](#4-reading-an-answer)
5. [Response Types and What to Do](#5-response-types-and-what-to-do)
6. [Tips for Better Questions](#6-tips-for-better-questions)
7. [Limitations and Scope](#7-limitations-and-scope)

---

## 1. What the Chatbot Does

The ShodhanaKG chatbot answers questions about the Ayurvedic biomedical knowledge graph.
It retrieves verified paths from ArcadeDB across 53 allowlisted traversal endpoints.
The knowledge graph contains 2,532 total nodes, 10,123 total relationships, 17 vertex types, 21 edge types covering compounds, purification methods, media, toxic substances, diseases, symptoms, dosha imbalances, and therapies.

Answers are assembled deterministically from retrieved graph records and literature citations.
Multi-hop queries require matching publication citations across intermediate nodes to prevent unsupported connections.
Retrieved facts cite 410 curated scientific publications indexed in the platform reference catalog.

Related technical documentation:
- [Chatbot Documentation](CHATBOT.md)

---

## 2. Opening and Using the Chatbot

### Opening the Assistant
Click the floating "Ask the graph" button in the bottom-right corner of any page.
Clicking the button opens the slide-over chat drawer.
You can close the panel by clicking the close button (`X`) or by pressing `Escape`.
The panel header provides the following controls:
- **Widen / Narrow panel**: Toggles between standard and expanded width.
- **New chat**: Clears conversation history and starts a fresh session.
- **Close assistant**: Closes the chat panel.

### Asking a Question
Type your question into the input field at the bottom of the panel.
The input field accepts text up to 500 characters.
Submit your question by pressing `Enter` or clicking the send button.
When starting a session, clickable starter question suggestions appear above the input area. Click any suggestion to submit it immediately.

### Asking Follow-Up Questions
The assistant tracks conversation context during your session.
Each query sends the last 20 conversational turns to the server.
You can ask follow-up questions about entities or findings mentioned in previous responses.

### Clearing Conversation History
Click the "New chat" button in the panel header.
Clearing history performs the following actions:
- Cancels any active streaming response.
- Resets the message list to the welcome message.
- Removes saved messages from browser session storage.
- Generates a unique conversation session identifier.

---

## 3. Example Questions

The following questions are taken directly from the repository evaluation cases and starter suggestions:

### Compound Questions
- What media is used for Copper?
- What toxic substances does Croton tiglium Linn. contain?

### Disease Questions
- What are the symptoms of Alopecia?
- What dosha imbalance is associated with Alopecia?

### Therapy Questions
- What diseases is Shirodhara used for?
- Which therapies are used to treat Alopecia?

### Integrated Questions
- What diseases can Shodhita Parada treat?
- What compounds treat Alopecia?

---

## 4. Reading an Answer

Answers present factual findings followed by supporting evidence.

### Summary and Heading
Each successful response includes:
- **Heading**: Formats the target category and seed entity (for example, "Media associated with Copper").
- **Introductory Summary**: Explains the association, noted constraints, and whether the finding draws on single or multi-study evidence.

### Numbered Results and Citation Badges
Target entities appear as a numbered list.
Each entry displays:
- The target entity name.
- The total count of supporting sources.
- A proportional frequency bar showing relative evidence volume.
- Numbered citation brackets (for example, `[1]`, `[2]`).

### Preview and "Show All" Toggle
The chat display initially previews the top 5 results.
If more than 5 results are returned, a button toggles between "Show all {N}" and "Show top 5".
Clicking "Show all {N}" expands the list to display every retrieved entity.

### Evidence Sheet and Evidence Cards
Clicking any citation bracket `[N]` or the "View all {N} sources" button opens the Evidence Sheet.
The evidence sheet is an accessible dialog that traps keyboard focus.
Pressing `Escape` closes the evidence sheet and returns focus to the chat list.

At the top of the sheet, filter buttons allow filtering by publication source:
- **All**: Shows all retrieved evidence records.
- **PubMed**: Restricts records to verified or declared PubMed citations.
- **AYUSH**: Restricts records to AYUSH Research Portal citations.

Each evidence card includes:
- The bracketed citation number (for example, `[1]`).
- Source and target entity names and node types.
- The relationship direction (for example, `Disease → Symptom`).
- Recorded properties, such as dosage or administration details.
- Treatment phase (if recorded in the graph).
- Detailed procedure text (if recorded in the graph).

### Publication Links
Evidence cards link directly to external scientific publications:
- **PubMed**: Displays the PubMed ID with an "Open ID →" link to the article on NCBI PubMed.
- **DOI**: Displays the DOI with an "Open ID →" link to the digital object identifier resolver.
- **AYUSH**: Displays "AYUSH ID" with a "Copy ID" button and a "Search AYUSH" link to the AYUSH Research Portal search interface.
- **Unclassified references**: Displays the raw citation string when an external identifier is unavailable.

---

## 5. Response Types and What to Do

The chatbot produces five primary response types:

### 1. Success (`success`)
- **Meaning**: The question matched a supported intent and found verified paths in the graph.
- **What to do**: Review the summary and numbered entity list. Click citation brackets or "View all {N} sources" to inspect evidence cards and publication links.

### 2. No Data (`no_data`)
- **Meaning**: The entity was recognized in the graph, but no edges exist for the requested relationship.
- **What to do**: Ask about a different relationship for that entity (for example, ask about purification methods instead of media), or inspect the entity directly in the Graph Explorer.

### 3. Needs Clarification (`needs_clarification`)
- **Meaning**: The entity name is ambiguous or matches multiple items across node types in the knowledge graph.
- **What to do**: The assistant displays candidate buttons showing entity names, node types, and exploration paths. Click the button corresponding to your intended entity.

### 4. Unsupported Question (`unsupported`)
- **Meaning**: The query falls outside the 53 allowlisted ArcadeDB traversal endpoints.
- **What to do**: Rephrase your question around supported relationships: purification methods, media, toxic substances, symptoms, doshas, formulations, or therapies.

### 5. Entity Not Found (`entity_not_found`)
- **Meaning**: The named entity could not be found in the knowledge graph dictionary.
- **What to do**: Check spelling or use classical Sanskrit, botanical binomial, or mineral terms (for example, "Parada", "Croton tiglium Linn.", "Tamaka Shwasa").

---

## 6. Tips for Better Questions

- **Use precise entity names**: Name specific compounds, diseases, or therapies (for example, "Copper", "Croton tiglium Linn.", "Alopecia", "Shirodhara").
- **Try classical or botanical names**: If a common English name is not recognized, try its Sanskrit or botanical term.
- **Focus on one relationship at a time**: Single-relation questions (such as asking for media, or asking for symptoms) return the cleanest results.
- **Avoid ambiguous pronouns**: Name the entity explicitly instead of asking "What else does it treat?".
- **Use clarification buttons**: When candidate buttons appear, click the candidate matching your target domain.
- **Use starter suggestions**: Review starter questions on a clean chat panel for examples of supported question structures.

---

## 7. Limitations and Scope

- **Supported question types**: Questions must map to the 53 allowlisted ArcadeDB traversal endpoints covering the 17 vertex types and 21 edge types in the curated knowledge graph. Questions outside this scope return an unsupported response.
- **Language support**: The chatbot accepts input in English. It recognizes standard transliterated Sanskrit and Ayurvedic terms.
- **Not medical advice**: The chatbot is an academic and scientific research tool. It does not provide medical advice, diagnosis, clinical recommendations, or individualized treatment plans.
