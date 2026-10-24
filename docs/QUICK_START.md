# 🎯 Quick Start - Provenance Feature

## ✅ Integration Complete!

The provenance feature is now **fully integrated** into the Disease Explorer.

## 🚀 How to Use (30 seconds)

### 1. Start Servers
```bash
# Terminal 1
# Requires PHP 5.6.40+
php -S localhost:8081 -t .

# Terminal 2
npm run dev
```

### 2. Open Browser
```
http://localhost:8080/explorer/disease/graph?disease=36
```

### 3. See the Magic
- **Graph loads** with Eczema and connections
- **Click on Eczema node** (center)
- **Right sidebar** shows two tabs: "Properties" | "Provenance"
- **Click "Provenance"** tab
- **See 3 verified paths** with green checkmarks!

## 📸 What You Should See

```
┌─────────────────┬──────────────────┬─────────────────┐
│   LEFT SIDEBAR  │   GRAPH (CENTER) │  RIGHT SIDEBAR  │
│                 │                  │                 │
│ Filters         │   [Eczema]◄──────┼─Properties──────┤
│ - Disease       │      │           │  Provenance ◄───┤ ◄── TAB
│ - Therapy       │   [Abhyanga]     │                 │
│ - Formulation   │      │           │ 3 paths found   │
│                 │   [Bala Taila]   │                 │
│                 │                  │ ✓ Provenance    │
│                 │   GREEN EDGES───►│   Verified      │
│                 │   (highlighted)  │                 │
│                 │                  │ Eczema → Abhyanga│
│                 │                  │   → Bala Taila  │
│                 │                  │                 │
│                 │                  │ PMID: 23723667  │
└─────────────────┴──────────────────┴─────────────────┘
```

## 🎨 Visual Guide

### Green Edges in Graph
- **Thick green edges** = Provenance validated (both edges share PubMed ID)
- **Dimmed edges** = Not in provenance paths
- Helps you visually spot verified connections

### Provenance Tab
- **Green badge**: "Provenance Verified" 
- **Path cards**: Disease → Therapy → Formulation hierarchy
- **PubMed links**: Click to open literature
- **Filter dropdown**: Show only specific PubMed sources
- **Clinical details**: Dose, duration, adjuvant

## 📊 Example Data (Eczema)

**3 provenance paths:**

1. **PMID 23723667**
   - Eczema → Abhyanga → Bala Taila
   - Duration: 1 Day

2. **PMID 21455456** 
   - Eczema → Abhyanga → Marichadi Taila
   - Duration: 3 Days

3. **PMID 21455456**
   - Eczema → Deepana Pachana → Panchakola Phanta
   - Dose: 50ml twice/day, Duration: 2 Days

## 🔧 Troubleshooting

**No Provenance tab?**
→ Make sure you clicked on a **Disease** node (not Therapy/Formulation)

**"No paths found"?**
→ Try disease ID 36 (Eczema) which has provenance data

**No green edges?**
→ Check that PHP server is running on port 8081

**Graph not loading?**
→ Check browser console (F12) for errors

## 📚 Documentation

- **[Frontend Documentation](./FRONTEND_DOCUMENTATION.md)** — Complete React component reference, graph visualization internals, styling, testing.
- **[Backend Documentation](./BACKEND_DOCUMENTATION.md)** — PHP API, ArcadeDB schema, endpoint reference, deployment guide.
- **[README](./README.md)** — Project overview, installation, screenshots.

## 🎓 What is Provenance?

**Provenance** = Data lineage showing evidence for connections

In this feature:
- Disease→Therapy edge has PubMed ID
- Therapy→Formulation edge has PubMed ID
- **When both match** = High confidence (same paper cited both)
- **Visual proof** = Green checkmark and highlighting

## ✨ Key Features

✅ Extracts 2-hop subgraphs (Disease→Therapy→Formulation)
✅ Validates provenance (matching PubMed IDs)
✅ Visual highlighting in graph (green edges)
✅ Detailed path cards with clinical data
✅ PubMed ID filtering
✅ Co-citation verification
✅ Fully integrated into Disease Explorer

## 🎉 Done!

Navigate to `/explorer/disease/graph`, select a disease, and see the Provenance tab!

**Build status:** ✅ Success
**Tests:** ✅ 8/8 passing
**Integration:** ✅ Complete
