/**
 * SortableHeader (story 5.3) — a `<th>` that sorts the table through a
 * button, carrying `aria-sort` and WP core's list-table sort classes
 * (`sortable` / `sorted` + `asc` / `desc`, `sorting-indicator` spans) so it
 * looks native next to any wp-admin list. A column that is not the current
 * sort key sorts ascending on first click; the current key flips direction.
 */
const ARIA_SORT = { asc: 'ascending', desc: 'descending' };

export default function SortableHeader( {
	column,
	label,
	orderby,
	order,
	onSort,
	className = '',
} ) {
	const isSorted = orderby === column;
	const nextOrder = isSorted && order === 'asc' ? 'desc' : 'asc';
	// Core convention (WP_List_Table::print_column_headers()): a sorted
	// header carries its current direction; an unsorted one carries the
	// opposite of the direction the next click produces (always `desc`:
	// an unsorted column sorts ascending first).
	const classes = [
		className,
		isSorted ? 'sorted' : 'sortable',
		isSorted ? order : 'desc',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<th
			scope="col"
			className={ classes }
			aria-sort={ isSorted ? ARIA_SORT[ order ] : 'none' }
		>
			<button
				type="button"
				className="pqm-sort-button"
				onClick={ () => onSort( column, nextOrder ) }
			>
				<span>{ label }</span>
				<span className="sorting-indicators">
					<span
						className="sorting-indicator asc"
						aria-hidden="true"
					/>
					<span
						className="sorting-indicator desc"
						aria-hidden="true"
					/>
				</span>
			</button>
		</th>
	);
}
