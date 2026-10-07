/**
 * CopyValue (story 36) — one value the user pastes into n8n: a read-only
 * field and a Copy button. navigator.clipboard where the browser allows it
 * (HTTPS); otherwise the field is selected and copied the old way, or left
 * selected for Ctrl+C — the same fallback as the n8n Workflow page's
 * import-URL copy (admin/js/pqm-admin.js).
 */
import { Button } from '@wordpress/components';
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {Object} props
 * @param {string} props.label Field label, e.g. "Name".
 * @param {string} props.value Value to copy.
 * @return {Element} Labelled field with its Copy button.
 */
export default function CopyValue( { label, value } ) {
	const field = useRef();
	const id = useId();
	const [ feedback, setFeedback ] = useState( '' );

	useEffect( () => {
		if ( ! feedback ) {
			return undefined;
		}
		const timer = setTimeout( () => setFeedback( '' ), 4000 );
		return () => clearTimeout( timer );
	}, [ feedback ] );

	const copied = __( 'Copied.', 'post-quality-manager' );

	const fallback = () => {
		field.current.select();
		let ok = false;
		try {
			ok = field.current.ownerDocument.execCommand( 'copy' );
		} catch ( e ) {
			// Keep the selection: the user copies by hand.
		}
		setFeedback(
			ok
				? copied
				: __(
						'Could not copy automatically. The value is selected — press Ctrl+C.',
						'post-quality-manager'
				  )
		);
	};

	const copy = () => {
		const clipboard = window.navigator.clipboard;
		if ( clipboard && window.isSecureContext ) {
			clipboard
				.writeText( value )
				.then( () => setFeedback( copied ), fallback );
		} else {
			fallback();
		}
	};

	return (
		<div className="pqm-copy-value">
			<div className="pqm-copy-field">
				<label htmlFor={ id } className="pqm-copy-label">
					{ label }
				</label>
				<input
					id={ id }
					ref={ field }
					type="text"
					className="code"
					value={ value }
					readOnly
					onFocus={ ( event ) => event.target.select() }
				/>
			</div>
			<Button
				variant="secondary"
				onClick={ copy }
				aria-label={ sprintf(
					/* translators: %s: label of the value to copy, e.g. "Name" */
					__( 'Copy %s', 'post-quality-manager' ),
					label
				) }
			>
				{ __( 'Copy', 'post-quality-manager' ) }
			</Button>
			<span className="pqm-copy-status" aria-live="polite">
				{ feedback }
			</span>
		</div>
	);
}
