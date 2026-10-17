import sys
import types
module = types.ModuleType('langchain_community.chat_models.vertexai')
module.ChatVertexAI = None
sys.modules['langchain_community.chat_models.vertexai'] = module

import ragas
print("Ragas imported successfully with patch!")
