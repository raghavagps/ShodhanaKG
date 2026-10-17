import { Users, Linkedin, GraduationCap, ExternalLink } from "lucide-react";

const Team = () => {
  return (
    <div className="container py-16 animate-fade-in">
      <div className="max-w-2xl mx-auto text-center mb-16">
        <div className="inline-flex items-center gap-2 rounded-full border border-herbal/20 bg-herbal/5 px-3 py-1 text-[11px] uppercase tracking-wider text-herbal font-semibold mb-4">
          <Users className="h-3 w-3" />
          Our Team
        </div>
        <h1 className="font-display text-4xl font-bold text-foreground mb-4">The ShodhanaKG Team</h1>
        <p className="text-muted-foreground">
          The multidisciplinary team behind the ShodhanaKG Integrative Ayurvedic Purification Knowledge Graph.
        </p>
      </div>

      {/* Principal Investigator Section */}
      <div className="max-w-3xl mx-auto mb-16">
        <h2 className="font-display text-2xl font-bold text-foreground mb-8 text-center">Principal Investigator</h2>
        <div className="group p-8 rounded-3xl border border-border bg-card shadow-card flex flex-col md:flex-row gap-8 items-center md:items-start hover:shadow-elevated transition-smooth">
          <div className="h-48 w-48 rounded-2xl bg-secondary shrink-0 overflow-hidden border-4 border-background shadow-md">
            <img 
              src={`${import.meta.env.BASE_URL}images/raghava_image.jpg`} 
              alt="Dr. Gajendra P. S. Raghava" 
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            />
          </div>
          <div className="text-center md:text-left space-y-4">
            <div>
              <h3 className="text-2xl font-bold text-foreground">Dr. Gajendra P. S. Raghava</h3>
              <p className="text-herbal font-semibold mt-1 uppercase tracking-wider text-sm">Principal Investigator</p>
            </div>
            <p className="text-muted-foreground leading-relaxed">
              Professor & Head of the Department (Computational Biology) at IIIT-Delhi. 
              Providing vision, guidance, and research leadership for the development of ShodhanaKG.
            </p>
            <div className="flex items-center justify-center md:justify-start gap-4 pt-2">
              <a 
                href="https://www.linkedin.com/in/raghavagps/" 
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-[#0077B5] transition-colors"
              >
                <Linkedin className="h-4 w-4" />
                LinkedIn
              </a>
              <a 
                href="https://scholar.google.co.in/citations?user=XK5GUiYAAAAJ&hl=en" 
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-herbal transition-colors"
              >
                <GraduationCap className="h-4 w-4" />
                Google Scholar
              </a>
            </div>
          </div>
        </div>
      </div>

      {/* Developers Section */}
      <div className="max-w-6xl mx-auto">
        <h2 className="font-display text-2xl font-bold text-foreground mb-8 text-center">Development Team</h2>
        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
          <div className="group p-8 rounded-2xl border border-border bg-card shadow-card text-center hover:shadow-elevated transition-smooth flex flex-col">
            <div className="h-32 w-32 rounded-full bg-secondary mx-auto mb-6 overflow-hidden border-4 border-background shadow-inner">
              <img 
                src={`${import.meta.env.BASE_URL}images/pushpendra_image.jpeg`} 
                alt="Pushpendra Singh Gahlot" 
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            </div>
            <h3 className="text-lg font-bold text-foreground">Pushpendra Singh Gahlot</h3>
            <p className="text-[11px] text-herbal font-semibold mt-1 uppercase tracking-wider">Research Lead & Developer</p>
            <p className="text-[11px] text-muted-foreground mt-4 leading-relaxed flex-1">
              DevOps, Server maintenance, study designing, and research lead for the platform architecture.
            </p>
            <div className="mt-6 pt-4 border-t border-border/50">
              <a 
                href="https://www.linkedin.com/in/pushpendra-singh-gahlot-196a3612b/" 
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-[#0077B5] transition-colors"
              >
                <Linkedin className="h-3.5 w-3.5" />
                LinkedIn Profile
              </a>
            </div>
          </div>

          <div className="group p-8 rounded-2xl border border-border bg-card shadow-card text-center hover:shadow-elevated transition-smooth flex flex-col">
            <div className="h-32 w-32 rounded-full bg-secondary mx-auto mb-6 overflow-hidden border-4 border-background shadow-inner">
              <img 
                src={`${import.meta.env.BASE_URL}images/Sakshi_image.jpg`} 
                alt="Sakshi Nagarkoti" 
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            </div>
            <h3 className="text-lg font-bold text-foreground">Sakshi Nagarkoti</h3>
            <p className="text-[11px] text-herbal font-semibold mt-1 uppercase tracking-wider">Researcher, Data & UI Developer</p>
            <p className="text-[11px] text-muted-foreground mt-4 leading-relaxed flex-1">
              Focused on Disease component curation, backend architecture, and user interface development.
            </p>
            <div className="mt-6 pt-4 border-t border-border/50">
              <a 
                href="https://www.linkedin.com/in/sakshi-nagarkoti-089935283" 
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-[#0077B5] transition-colors"
              >
                <Linkedin className="h-3.5 w-3.5" />
                LinkedIn Profile
              </a>
            </div>
          </div>

          <div className="group p-8 rounded-2xl border border-border bg-card shadow-card text-center hover:shadow-elevated transition-smooth flex flex-col">
            <div className="h-32 w-32 rounded-full bg-secondary mx-auto mb-6 overflow-hidden border-4 border-background shadow-inner">
              <img 
                src={`${import.meta.env.BASE_URL}images/ritika_image.jpeg`} 
                alt="Ritika Sood" 
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            </div>
            <h3 className="text-lg font-bold text-foreground">Ritika Sood</h3>
            <p className="text-[11px] text-herbal font-semibold mt-1 uppercase tracking-wider">Researcher, Data & UI/UX Developer</p>
            <p className="text-[11px] text-muted-foreground mt-4 leading-relaxed flex-1">
              Focused on Compound component curation, database management, and UI/UX design.
            </p>
            <div className="mt-6 pt-4 border-t border-border/50">
              <a 
                href="https://www.linkedin.com/in/ritika-sood-b734b122a/" 
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-[#0077B5] transition-colors"
              >
                <Linkedin className="h-3.5 w-3.5" />
                LinkedIn Profile
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Team;
