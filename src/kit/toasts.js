/**
 * Toasts (story 5.4; in the kit since 5.6, shared by Post Reviews and the
 * Dashboard) — local notice state rendered through the
 * SnackbarList, so trigger confirmations and polling outcomes surface
 * without a `core/notices` store or any `wp-data` dependency. Each
 * Snackbar announces its own message through `speak()` on mount, so the
 * wrapper is deliberately *not* a live region, and both host pages render
 * it outside any live region (Post Reviews keeps its one `role="status"`
 * host, the "Updating…" line, separate). Each toast removes itself after
 * five seconds; clicking one dismisses it early.
 */
import { SnackbarList } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

const TOAST_TTL = 5000;

let nextId = 0;

/**
 * Toast state for one page. `addToast( content, status )` with status
 * 'success' | 'info' | 'error' | 'warning' (SnackbarList's own set).
 */
export function useToasts() {
	const [ toasts, setToasts ] = useState( [] );
	const timers = useRef( new Map() );

	const removeToast = useCallback( ( id ) => {
		clearTimeout( timers.current.get( id ) );
		timers.current.delete( id );
		setToasts( ( prev ) => prev.filter( ( t ) => t.id !== id ) );
	}, [] );

	const addToast = useCallback(
		( content, status = 'info' ) => {
			const id = `pqm-toast-${ ++nextId }`;
			setToasts( ( prev ) => [ ...prev, { id, content, status } ] );
			timers.current.set(
				id,
				setTimeout( () => removeToast( id ), TOAST_TTL )
			);
		},
		[ removeToast ]
	);

	// Unmount: drop pending timers so none fires on a gone component.
	useEffect( () => {
		const pending = timers.current;
		return () => pending.forEach( clearTimeout );
	}, [] );

	return { toasts, addToast, removeToast };
}

export default function Toasts( { toasts, onRemove } ) {
	return (
		<div className="pqm-toasts">
			<SnackbarList
				notices={ toasts.map( ( t ) => ( {
					...t,
					isDismissible: true,
				} ) ) }
				onRemove={ onRemove }
			/>
		</div>
	);
}
