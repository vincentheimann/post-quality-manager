/**
 * ProgressPills (story 5.2) — one pill per command, always the full
 * canonical row: ran commands in the blue ✓ variant, un-run commands
 * neutral. Unknown ran commands append after the canonical six so data
 * is never hidden.
 */
import { COMMAND_ORDER, commandLabel } from './copy';

import './style.css';

export default function ProgressPills( { commands = [] } ) {
	const all = [
		...COMMAND_ORDER,
		...commands.filter( ( c ) => ! COMMAND_ORDER.includes( c ) ),
	];

	return (
		<div className="pqm-progress-pills">
			{ all.map( ( cmd ) => {
				const ran = commands.includes( cmd );
				return (
					<span
						key={ cmd }
						className={
							ran
								? 'pqm-progress-pill is-ran'
								: 'pqm-progress-pill'
						}
					>
						{ ran ? '✓ ' : '' }
						{ commandLabel( cmd ) }
					</span>
				);
			} ) }
		</div>
	);
}
