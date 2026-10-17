import { Mail, Phone, MapPin, Globe, ExternalLink, MessageSquare } from "lucide-react";

const Contact = () => {
  return (
    <div className="container py-16 animate-fade-in">
      <div className="max-w-4xl mx-auto">
        <div className="text-center mb-12">
          <h1 className="font-display text-4xl font-bold text-foreground mb-4">Contact Us</h1>
          <p className="text-muted-foreground text-lg max-w-2xl mx-auto">
            If you have any comments, compliments, or queries regarding <span className="font-semibold text-foreground">ShodhanaKG</span>, please feel free to contact us at the address below.
          </p>
        </div>

        <div className="bg-card border border-border rounded-2xl p-8 md:p-12 shadow-card flex flex-col md:flex-row gap-10 items-start">
          {/* Profile Image */}
          <div className="w-full md:w-64 shrink-0">
            <img 
              src={`${import.meta.env.BASE_URL}images/raghava_image.jpg`} 
              alt="Dr. Gajendra P. S. Raghava" 
              className="w-full aspect-[4/5] object-cover rounded-xl shadow-md border border-border"
            />
          </div>

          <div className="flex-1 space-y-6">
            <div>
              <h2 className="font-display text-3xl font-bold text-foreground">Dr. Gajendra P. S. Raghava</h2>
              <p className="text-herbal font-medium text-lg mt-1">Professor & Head of the Department (Computational Biology)</p>
            </div>

            <div className="space-y-4 pt-2">
              <div className="flex items-start gap-3 text-muted-foreground">
                <MapPin className="h-5 w-5 text-terracotta shrink-0 mt-0.5" />
                <span>Indraprastha Institute of Information Technology, Okhla Phase 3, New Delhi, India</span>
              </div>

              <div className="flex items-start gap-3 text-muted-foreground">
                <Mail className="h-5 w-5 text-herbal shrink-0 mt-0.5" />
                <div className="flex flex-col gap-1">
                  <a href="mailto:raghava@iiitd.ac.in" className="hover:text-foreground hover:underline transition-smooth">raghava@iiitd.ac.in</a>
                  <a href="mailto:raghavagps@gmail.com" className="hover:text-foreground hover:underline transition-smooth">raghavagps@gmail.com</a>
                  <a href="mailto:raghavagps@yahoo.com" className="hover:text-foreground hover:underline transition-smooth">raghavagps@yahoo.com</a>
                </div>
              </div>

              <div className="flex items-start gap-3 text-muted-foreground">
                <Globe className="h-5 w-5 text-bark shrink-0 mt-0.5" />
                <a 
                  href="http://webs.iiitd.edu.in/raghava/" 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  className="flex items-center gap-1.5 hover:text-foreground hover:underline transition-smooth"
                >
                  http://webs.iiitd.edu.in/raghava/
                  <ExternalLink className="h-3.5 w-3.5" />
                </a>
              </div>

              <div className="flex items-start gap-3 text-muted-foreground">
                <Phone className="h-5 w-5 text-herbal shrink-0 mt-0.5" />
                <a href="tel:+9126907444" className="hover:text-foreground hover:underline transition-smooth">+91-26907444</a>
              </div>
            </div>
          </div>
        </div>

        <div className="mt-12 p-8 rounded-2xl bg-secondary/20 border border-border/50 text-center">
          <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-background shadow-sm mb-4">
            <MessageSquare className="h-6 w-6 text-herbal" />
          </div>
          <h3 className="text-xl font-bold mb-2">Technical Support</h3>
          <p className="text-muted-foreground text-sm max-w-lg mx-auto">
            For technical issues regarding the Knowledge Graph suite, please include your browser version and the specific explorer mode you were using.
          </p>
        </div>
      </div>
    </div>
  );
};

export default Contact;
