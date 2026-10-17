import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { HelpCircle } from "lucide-react";
import { Link } from "react-router-dom";

const FAQ = () => {
  const faqs = [
    {
      question: "What is ShodhanaKG?",
      answer: "ShodhanaKG is an Integrative Ayurvedic Purification Knowledge Graph designed to bridge the gap between traditional Ayurvedic knowledge and modern biomedical research. It comprises a Disease-Shodhana KG and a Compound-Shodhana KG, which are combined into an overarching Integrated KG."
    },
    {
      question: "What data sources are included in this Knowledge Graph?",
      answer: "The data sources for ShodhanaKG include validated research articles extracted from peer-reviewed journals, alongside recognized scientific and traditional medicine databases such as PubMed, AYUSH, and DHARA."
    },
    {
      question: "How can I search for specific diseases or compounds?",
      answer: "You can use the global search bar in the navigation header to find any entity by name. Alternatively, you can use the specific Explorer modes (Disease, Compound, or Integrated) to browse and filter entities within those domains."
    },
    {
      question: "What information is shown in the Graph View?",
      answer: "The Graph View visualizes the relationships between different entities. For example, selecting a disease will show its connected symptoms, doshas, therapies, and Symptoms. You can interact with nodes to view their details and properties in the right panel, along with their reference IDs."
    },
    {
      question: "How can I export the data?",
      answer: "Each explorer view (Graph and Table) provides export options. In the Graph View, you can export as PNG, JSON, or CSV. In the Table View, you can download the filtered results as a CSV file for external analysis."
    },
    {
      question: "Who can benefit from using ShodhanaKG?",
      answer: "ShodhanaKG is designed for researchers, Ayurveda practitioners, students, clinicians, and anyone interested in exploring the scientific and clinical knowledge behind Ayurvedic purification and therapies. All information provided on the platform is for research and educational purposes only and is not intended for medical diagnosis, treatment, or clinical decision-making."
    }
  ];

  return (
    <div className="container py-12 max-w-4xl animate-fade-in">
      <div className="flex items-center gap-3 mb-8">
        <div className="p-2 rounded-lg bg-primary/10 text-primary">
          <HelpCircle className="h-6 w-6" />
        </div>
        <div>
          <h1 className="text-3xl font-display font-bold text-foreground">Frequently Asked Questions</h1>
          <p className="text-muted-foreground mt-1">Common questions about the ShodhanaKG platform and data.</p>
        </div>
      </div>

      <Card className="border-border/60 bg-card/50 backdrop-blur-sm shadow-card">
        <CardHeader>
          <CardTitle className="text-xl font-display">General Information</CardTitle>
        </CardHeader>
        <CardContent>
          <Accordion type="single" collapsible className="w-full">
            {faqs.map((faq, index) => (
              <AccordionItem key={index} value={`item-${index}`} className="border-border/60">
                <AccordionTrigger className="text-left font-medium hover:text-primary transition-smooth py-4">
                  {faq.question}
                </AccordionTrigger>
                <AccordionContent className="text-muted-foreground leading-relaxed pb-4">
                  {faq.answer}
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </CardContent>
      </Card>

      <div className="mt-12 p-6 rounded-xl bg-primary/5 border border-primary/10 text-center">
        <h3 className="font-display font-semibold text-lg text-primary mb-2">Still have questions?</h3>
        <p className="text-sm text-muted-foreground mb-4">
          Can't find the answer you're looking for? Feel free to reach out to our research team.
        </p>
        <Link to="/contact" className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow hover:bg-primary/90 h-9 px-4 py-2">
          Contact Us
        </Link>
      </div>
    </div>
  );
};

export default FAQ;
