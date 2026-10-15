/**
 * Builds an installable plugin zip locally, as the release's package job
 * does (.github/workflows/release.yml):
 *
 *   npm run zip
 *
 * refuses a working tree with uncommitted or untracked changes, or git-ignored
 * files inside post-quality-manager/ other than the build (the zip then
 * matches a commit), runs `npm run build` into an emptied admin/build/ (not
 * in git), checks the bundle exists, and zips the post-quality-manager/ folder,
 * root folder kept, as dist/post-quality-manager-<branch>.zip. Install it in
 * wp-admin with Plugins → Add New → Upload Plugin → Replace current with
 * uploaded. Windows zips with its own tar.exe (Git Bash's GNU tar writes no
 * zip; PowerShell's Compress-Archive can write backslash paths); elsewhere
 * with `zip -r`, as the release does. The version stays the header's: the
 * file name says which branch it was built from.
 * Skipped: `npm ci` before the build, as the release runs it — add when a
 * stale node_modules ships a wrong bundle.
 * Repository tooling: not in the zip.
 */

const { execFileSync, execSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const run = ( command ) =>
	execSync( command, { cwd: root, encoding: 'utf8' } ).trim();

if ( run( 'git status --porcelain --untracked-files=all' ) !== '' ) {
	console.error(
		'The working tree has uncommitted changes: commit or stash them first, so the zip matches a commit.'
	);
	process.exit( 1 );
}
// Git-ignored files in the plugin folder would ship too (e.g. a .claude/
// settings.local.json a global ignore hides): only the build folder may be there.
const ignored = run( 'git status --porcelain --ignored --untracked-files=all -- post-quality-manager' )
	.split( '\n' )
	.filter( ( line ) => line !== '' && ! line.startsWith( '!! post-quality-manager/admin/build/' ) );
if ( ignored.length ) {
	console.error( `Remove these git-ignored files from the plugin folder first:\n${ ignored.join( '\n' ) }` );
	process.exit( 1 );
}

let branch = run( 'git rev-parse --abbrev-ref HEAD' );
if ( branch === 'HEAD' ) {
	branch = run( 'git rev-parse --short HEAD' ); // detached: a tag or a commit
}

// From empty: wp-scripts' clean keeps fonts/ and images/ an older build left.
fs.rmSync( path.join( root, 'post-quality-manager/admin/build' ), { recursive: true, force: true } );
execSync( 'npm run build', { cwd: root, stdio: 'inherit' } );
for ( const file of [ 'index.js', 'index.asset.php' ] ) {
	if ( ! fs.existsSync( path.join( root, 'post-quality-manager/admin/build', file ) ) ) {
		console.error( `The build did not write admin/build/${ file }.` );
		process.exit( 1 );
	}
}

const zip = path.join(
	'dist',
	`post-quality-manager-${ branch.replace( /[^A-Za-z0-9._-]+/g, '-' ) }.zip`
);
fs.mkdirSync( path.join( root, 'dist' ), { recursive: true } );
fs.rmSync( path.join( root, zip ), { force: true } ); // `zip -r` would add to an old one
if ( process.platform === 'win32' ) {
	const tar = path.join( process.env.SystemRoot || 'C:\\Windows', 'System32', 'tar.exe' );
	execFileSync( tar, [ '-a', '-cf', zip, 'post-quality-manager' ], { cwd: root, stdio: 'inherit' } );
} else {
	execFileSync( 'zip', [ '-rq', zip, 'post-quality-manager' ], { cwd: root, stdio: 'inherit' } );
}

console.log( `\n${ path.join( root, zip ) }` );
console.log( 'Install it with Plugins → Add New → Upload Plugin → Replace current with uploaded.' );
