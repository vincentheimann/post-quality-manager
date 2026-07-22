# n8n Workflow — Flow Diagram

The canonical workflow template ships with the plugin at
`post-quality-manager/templates/n8n-workflow.json` and is downloaded pre-configured
from **Settings → Workflow File**. Triggers are agnostic: the WordPress plugin
(dashboard button or WP-Cron) POSTs to the webhook; any chat tool or automation can
call the same contract. Results go back to the WordPress callback endpoint
(`/save-review`) and, optionally, to a notification webhook (Slack, kChat, Teams, …).

```mermaid
flowchart TD
    A[Webhook Trigger<br>POST from WP plugin or any client] --> B[Parse & Route Input]
    B --> C{Route Command<br>10 branches}

    C -->|score| D[Get Post Data]
    D --> D2[Format Score Report] --> NOTIFY

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

    G -->|review| R1[Build Review Body] --> P1[Claude - Review Agent] --> R1F[Format Review Report] --> CB[POST Result to WordPress<br>/save-review] --> NOTIFY
    G -->|takeaways| R2[Build Takeaways Body] --> P2[Claude - Takeaways Agent] --> R2F[Format Takeaways Report] --> NOTIFY
    R2F --> AutoT[Trigger Auto Apply] --> ApplyT[Apply Takeaways → Update WP Post → Confirmation → Notify]

    G -->|sources| R3[Build Sources Body] --> P3[Claude - Sources Agent] --> R3F[Format Sources Report] --> NOTIFY
    R3F --> AutoS[Trigger Auto Apply Sources] --> ApplyS[Apply Sources → Update WP Post → Confirmation → Notify]

    G -->|rewrite| R4[Build Rewrite Body] --> P4[Claude - Rewrite Agent] --> RW1[Parse Rewrite Response] --> RW2[Prepare Rewrite for WP] --> RW3[Update WP Post] --> RW4[Format Rewrite Confirmation] --> NOTIFY

    G -->|seo| R5[Build SEO Body] --> P5[Claude - SEO Agent] --> R5F[Format SEO Report] --> NOTIFY

    G -->|apply| AP{Route Apply Subcommand}
    AP -->|takeaways| CT{Check Takeaways Cache}
    CT -->|hit| ApplyT
    CT -->|miss| P2

    AP -->|sources| CS{Check Sources Cache}
    CS -->|hit| ApplyS
    CS -->|miss| P3

    G -->|audit| R6[Build Audit Body] --> P6[Claude - Audit Agent] --> R6F[Format Audit Report] --> NOTIFY

    C -->|help| HLP[Format Help Message] --> NOTIFY
    C -->|error| ERR[Send error] --> NOTIFY

    NOTIFY([Send Notification<br>optional webhook: Slack / kChat / Teams])
```
