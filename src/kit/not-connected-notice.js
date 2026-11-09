/**
 * NotConnectedNotice (ticket 32; moved from src/post-reviews-table.js in
 * story 109) — n8n is not connected: why runs cannot start, and the next
 * step — the n8n Workflow page for admins, an administrator for everyone
 * else. Shown by Post Reviews above its filters and by the post editor's
 * meta box in place of its action.
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './style.css';

/**
 * @param {Object}  props
 * @param {boolean} props.canManage Whether the viewer may open the n8n Workflow page.
 */
export default function NotConnectedNotice( { canManage } ) {
	return (
		<Notice
			status="warning"
			isDismissible={ false }
			className="pqm-connect-notice"
			actions={
				canManage
					? [
							{
								label: __(
									'Set up the n8n workflow',
									'post-quality-manager'
								),
								url: 'admin.php?page=postquality-workflow',
								variant: 'primary',
							},
						]
					: []
			}
		>
			<strong>
				{ __(
					'n8n is not connected yet, so agents cannot run.',
					'post-quality-manager'
				) }
			</strong>{ ' ' }
			{ canManage
				? __(
						'Import the pre-configured workflow into n8n and connect it to this site — about 10 minutes, one time.',
						'post-quality-manager'
					)
				: __(
						'Ask an administrator to connect the n8n workflow to this site.',
						'post-quality-manager'
					) }
		</Notice>
	);
}
