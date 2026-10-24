import sys
import types
module = types.ModuleType('langchain_community.chat_models.vertexai')
module.ChatVertexAI = None
sys.modules['langchain_community.chat_models.vertexai'] = module

import json
import os
import pandas as pd
from datasets import Dataset

try:
    from ragas import evaluate
    from ragas.metrics import faithfulness, answer_relevancy
    from ragas.run_config import RunConfig
except ImportError:
    print("Error: RAGAS is not installed properly.")
    exit(1)

from langchain_ollama import ChatOllama
from langchain_ollama import OllamaEmbeddings
from ragas.llms import LangchainLLMWrapper
from ragas.embeddings import LangchainEmbeddingsWrapper

def main():
    dataset_path = os.path.join(os.path.dirname(__file__), 'fixtures', 'ragas_dataset.json')
    output_path = os.path.join(os.path.dirname(__file__), 'fixtures', 'ragas_results.csv')
    
    with open(dataset_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
        
    valid_data = {
        "question": [],
        "answer": [],
        "contexts": []
    }
    
    for row in data:
        if not row["answer"].startswith("Error generating") and not row["answer"].startswith("No answer text"):
            valid_data["question"].append(row["question"])
            valid_data["answer"].append(row["answer"])
            valid_data["contexts"].append(row["contexts"])
            
    dataset = Dataset.from_dict(valid_data)
    
    ollama_model = "llama3.2" 
    
    try:
        # CRITICAL FIX: Add format="json" to force the smaller local model to return strictly parseable JSON
        chat_model = ChatOllama(model=ollama_model, temperature=0, format="json")
        evaluator_llm = LangchainLLMWrapper(chat_model)
        
        embed_model = OllamaEmbeddings(model=ollama_model)
        evaluator_embeddings = LangchainEmbeddingsWrapper(embed_model)
    except Exception as e:
        print(f"Failed to initialize Ollama: {e}")
        return

    print("Running RAGAS evaluation (Configured for strict JSON output)...")
    
    run_config = RunConfig(timeout=1200, max_retries=3, max_workers=1, max_wait=300)
    
    result = evaluate(
        dataset,
        metrics=[faithfulness, answer_relevancy],
        llm=evaluator_llm,
        embeddings=evaluator_embeddings,
        run_config=run_config,
        raise_exceptions=False
    )
    
    print("\n=== EVALUATION RESULTS ===")
    print(result)
    
    df = result.to_pandas()
    df.to_csv(output_path, index=False)
    print(f"\nDetailed evaluation results saved to: {output_path}")

if __name__ == "__main__":
    main()
