flowchart TD
    A[kChat Trigger<br>Webhook POST] --> B[Parse & Route Input]
    B --> C{Route Command<br>9 branches}
    C -->|score| D[Get Post Data → Format Score → kChat]
    C -->|review/takeaways/sources/rewrite/seo| E[Get Post Data + Guidelines + Block Rules]
    E --> F[Merge Post + Guidelines]
    F --> G{Route to Agent}
    G -->|review| H[Claude Review → Format → kChat]
    G -->|takeaways| I[Claude Takeaways → Format → kChat]
    G -->|sources| J[Claude Sources → Format → kChat]
    G -->|rewrite| K[Claude Rewrite → Parse → Gutenberg → WP PATCH → kChat]
    G -->|seo| L[Claude SEO → Format → kChat]
    G -->|apply| M{Route Apply Subcommand}
    M -->|takeaways| N{Cache hit?}
    N -->|yes| O[Apply Takeaways → WP PATCH → kChat]
    N -->|no| P[Run Takeaways Review first → Auto Apply]
    M -->|sources| Q{Cache hit?}
    Q -->|yes| R[Apply Sources → WP PATCH → kChat]
    Q -->|no| S[Run Sources Review first → Auto Apply]
    C -->|help| T[Format Help → kChat]
    C -->|error| U[Send error → kChat]