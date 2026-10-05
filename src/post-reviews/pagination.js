/**
 * Pagination (story 5.3) — WP core's `tablenav-pages` markup (item count,
 * previous / next, "page N of M") driven by the REST `total` / `pages`.
 * Server-side paging only: the page is a query param, never a slice of
 * what was fetched. A stale page beyond `pages` still shows "N of M" so
 * the state is honest; the table above renders its empty state.
 */
import { Button } from '@wordpress/components';
import { _n, __, sprintf } from '@wordpress/i18n';

export default function Pagination( { total, page, pages, onChange } ) {
	return (
		<div className="tablenav bottom pqm-tablenav">
			<div
				className={ `tablenav-pages${ pages > 1 ? '' : ' one-page' }` }
			>
				<span className="displaying-num">
					{ sprintf(
						/* translators: %d: number of posts matching the current filters */
						_n(
							'%d item',
							'%d items',
							total,
							'post-quality-manager'
						),
						total
					) }
				</span>
				{ /* Also shown for a stale page beyond `pages`, so "N of M"
				     and "previous" stay reachable without clearing filters. */ }
				{ ( pages > 1 || page > 1 ) && (
					<span className="pagination-links">
						<Button
							variant="secondary"
							className="prev-page"
							disabled={ page <= 1 }
							onClick={ () => onChange( page - 1 ) }
							label={ __(
								'Previous page',
								'post-quality-manager'
							) }
							showTooltip={ false }
						>
							<span aria-hidden="true">‹</span>
						</Button>
						<span className="paging-input">
							<span className="tablenav-paging-text">
								{ sprintf(
									/* translators: 1: current page number, 2: total number of pages */
									__(
										'%1$d of %2$d',
										'post-quality-manager'
									),
									page,
									pages
								) }
							</span>
						</span>
						<Button
							variant="secondary"
							className="next-page"
							disabled={ page >= pages }
							onClick={ () => onChange( page + 1 ) }
							label={ __( 'Next page', 'post-quality-manager' ) }
							showTooltip={ false }
						>
							<span aria-hidden="true">›</span>
						</Button>
					</span>
				) }
			</div>
		</div>
	);
}
