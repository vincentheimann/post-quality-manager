flowchart TD
    A[kChat Trigger<br>Webhook POST] --> B[Parse & Route Input]
    B --> C{Route Command<br>10 branches}

    C -->|score| D[Get Post Data]
    D --> D2[Format Score Report] --> kChat

    C -->|review| E1[Get Post Data + Guidelines]
    C -->|takeaways| E1
    C -->|sources| E1
    C -->|seo| E1
    C -->|apply| E1
    C -->|audit| E1

    C -->|rewrite| E2[Get Post Data + Guidelines<br>+ Block Rules + Agent Instructions]

    E1 --> F[Merge Post + Guidelines]
    E2 --> F

    F --> G{Route to Agent}

    G -->|review| R1[Build Review Body] --> P1[Processing Review / Claude - Review Agent] --> R1F[Format Review Report] --> kChat
    G -->|takeaways| R2[Build Takeaways Body] --> P2[Processing Takeaways / Claude - Takeaways Agent] --> R2F[Format Takeaways Report] --> kChat
    R2F --> AutoT[Trigger Auto Apply] --> ApplyT[Apply Takeaways → Update WP Post - Takeaways → Format Apply Confirmation → kChat]

    G -->|sources| R3[Build Sources Body] --> P3[Processing Sources / Claude - Sources Agent] --> R3F[Format Sources Report] --> kChat
    R3F --> AutoS[Trigger Auto Apply Sources] --> ApplyS[Apply Sources → Update WP Post - Sources → Format Apply Sources Confirmation → kChat]

    G -->|rewrite| R4[Build Rewrite Body] --> P4[Processing Rewrite / Claude - Rewrite Agent] --> RW1[Parse Rewrite Response] --> RW2[Prepare Rewrite for WP] --> RW3[Update WP Post] --> RW4[Format Rewrite Confirmation] --> kChat

    G -->|seo| R5[Build SEO Body] --> P5[Processing SEO / Claude SEO Agent] --> R5F[Format SEO Report] --> kChat

    G -->|apply| AP{Route Apply Subcommand}
    AP -->|takeaways| CT{Check Takeaways Cache}
    CT -->|hit| ApplyT
    CT -->|miss| P2

    AP -->|sources| CS{Check Sources Cache}
    CS -->|hit| ApplyS
    CS -->|miss| P3

    G -->|audit| R6[Build Audit Body] --> P6[Processing Audit / Claude - Audit Agent] --> R6F[Format Audit Report] --> kChat

    C -->|help| HLP[Format Help Message] --> kChat
    C -->|error| ERR[Send error] --> kChat

    kChat([Send to kChat])