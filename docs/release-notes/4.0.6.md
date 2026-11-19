## Upgrading from an earlier version

**A setup or an update that works now says so, even when n8n's first run is slow.** After the plugin sets up your n8n workflow or you choose Update workflow, a callback from the workflow's first run that comes a few seconds late no longer reports the setup or the update as failed; before, the n8n Workflow page could say n8n could not reach this site. When n8n really cannot call this site back, the plugin now waits about half a minute before it says so. Nothing to do.
