#!/usr/bin/php
<?php

require_once( __DIR__ . '/github-api.php' );
require_once( __DIR__ . '/misc.php' );
require_once( __DIR__ . '/phpcs-scan.php' );
require_once( __DIR__ . '/lint-scan.php' );

/*
 * Handle boolean parameters given on the command-line.
 *
 * Will set a default value for the given parameter name,
 * if no value is set. Will then proceed to check if the
 * value given is a boolean and will then convert the value
 * to a boolean-type, and finally set it in $options.
 */

function vipgoci_option_bool_handle(
	&$options,
	$parameter_name,
	$default_value
) {

	/* If no default is given, set it */
	if ( ! isset( $options[ $parameter_name ] ) ) {
		$options[ $parameter_name ] = $default_value;
	}

	/* Check if the gien value is a false or true */
	if (
		( $options[ $parameter_name ] !== 'false' ) &&
		( $options[ $parameter_name ] !== 'true' )
	) {
		print 'Usage: Parameter --' . $parameter_name .
			' has to be either false or true' . "\n";

		exit( 253 );
	}

	/* Convert the given value to a boolean type value */
	if ( $options[ $parameter_name ] === 'false' ) {
		$options[ $parameter_name ] = false;
	}

	else {
		$options[ $parameter_name ] = true;
	}
}


/*
 * Determine exit status.
 *
 * If any 'error'-type issues were submitted to
 * GitHub we announce a failure to our parent-process
 * by returning with a non-zero exit-code.
 *
 * If we only submitted warnings, we do not announce failure.
 */

function vipgoci_exit_status( $results ) {
	foreach (
		array_keys(
			$results['stats']
		)
		as $stats_type
	) {
		foreach (
			array_keys(
				$results['stats'][ $stats_type ]
			)
			as $pr_number
		) {
			if (
				0 !== $results['stats']
					[ $stats_type ]
					[ $pr_number ]
					['error']
			) {
				// Some errors were found, return non-zero
				return 250;
			}
		}

	}

	return 0;
}


/*
 * Main invocation function.
 */
function vipgoci_run() {
	global $argv;

	$startup_time = time();

	$options = getopt(
		null,
		array(
			'repo-owner:',
			'repo-name:',
			'commit:',
			'token:',
			'output:',
			'dry-run:',
			'phpcs-path:',
			'local-git-repo:',
			'help',
			'lint:',
			'phpcs:',
		)
	);

	// Validate args
	if (
		! isset( $options['repo-owner'] ) ||
		! isset( $options['repo-name'] ) ||
		! isset( $options['commit'] ) ||
		! isset( $options['token'] ) ||
		isset( $options['help'] )
	) {
		print 'Usage: ' . $argv[0] . "\n" .
			"\t" . '--repo-owner=owner --repo-name=name --commit=SHA --token=string' . "\n" .
			"\t" . '--phpcs-path=string' . "\n" .
			"\t" . '[ --local-git-repo=path ] [ --dry-run=boolean ] [ --output=file-path ]' . "\n" .
			"\t" . '[ --phpcs=true ] [ --lint=true ]' . "\n" .
			"\n" .
			"\t" . '--repo-owner        Specify repository owner, can be an organization' . "\n" .
			"\t" . '--repo-name         Specify name of the repository' . "\n" .
			"\t" . '--commit            Specify the exact commit to scan' . "\n" .
			"\t" . '--token             The access-token to use to communicate with GitHub' . "\n" .
			"\t" . '--phpcs-path        Full path to PHPCS script' . "\n" .
			"\t" . '--local-git-repo    The local git repository to use for raw-data' . "\n" .
                        "\t" . '                    -- this will save requests to GitHub, speeding up the' . "\n" .
                        "\t" . '                    whole process' . "\n" .
			"\t" . '--dry-run           If set to true, will not make any changes to any data' . "\n" .
			"\t" . '                    on GitHub -- no comments will be submitted, etc.' . "\n" .
			"\t" . '--output            Where to save output made from running PHPCS' . "\n" .
			"\t" . '                    -- this should be a filename' . "\n" .
			"\t" . '--phpcs             Whether to run PHPCS' . "\n" .
			"\t" . '--lint              Whether to do PHP linting' . "\n" .
			"\t" . '--help              Displays this message' . "\n";

		exit( 253 );
	}


	/*
	 * Check if PHPCS executable is defined, and
	 * if it is a file.
	 */

	if (
		( ! isset( $options['phpcs-path'] ) ) ||
		( ! is_file( $options['phpcs-path'] ) )
	) {
		print 'Usage: Parameter --phpcs-path' .
			' has to be a valid path to PHPCS' . "\n";

		exit( 253 );

	}

	/*
	 * Handle optional --local-git-repo parameter
	 */

	if ( isset( $options['local-git-repo'] ) ) {
		$options['local-git-repo'] = rtrim(
			$options['local-git-repo'],
			'/'
		);

		if ( false === is_dir(
			$options['local-git-repo'] . '/.git'
		) ) {
			vipgoci_log(
				'Local git repository was not found',
				array(
					'local_git_repo' =>
						$options['local-git-repo'],
				)
			);

			$options['local-git-repo'] = null;
		}
	}


	/*
	 * Handle boolean parameters parameter
	 */

	vipgoci_option_bool_handle( $options, 'dry-run', 'false' );

	vipgoci_option_bool_handle( $options, 'phpcs', 'true' );

	vipgoci_option_bool_handle( $options, 'lint', 'true' );


	if (
		( false === $options['lint'] ) &&
		( false === $options['phpcs'] )
	) {
		vipgoci_log(
			'Both --lint and --phpcs set to false, nothing to do!',
			array()
		);

		exit( 253 );
	}


	/*
	 * Run all checks and store the results in an array
	 */

	vipgoci_log(
		'Starting up...',
		array(
			'options' => $options
		)
	);

	$results = array(
		'issues'	=> array(),

		'stats'		=> array(
			'phpcs'	=> null,
			'lint'	=> null,
		),
	);

	if ( true === $options['lint'] ) {
		vipgoci_lint_scan_commit(
			$options,
			$results['issues'],
			$results['stats']['lint']
		);
	}

	/*
	 * Note: We run this, even if linting fails, to make sure
	 * to catch all errors incrementally.
	 */

	if ( true === $options['phpcs'] ) {
		vipgoci_phpcs_scan_commit(
			$options,
			$results['issues'],
			$results['stats']['phpcs']
		);
	}


	/*
	 * Submit any issues to GitHub
	 */
	vipgoci_github_review_submit(
		$options['repo-owner'],
		$options['repo-name'],
		$options['token'],
		$options['commit'],
		$results,
		$options['dry-run']
	);

	vipgoci_log(
		'Shutting down',
		array(
			'run_time_seconds'	=> time() - $startup_time,
			'results'		=> $results,
		)
	);


	return vipgoci_exit_status(
		$results
	);
}

$ret = vipgoci_run();

exit( $ret );