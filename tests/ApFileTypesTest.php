<?php

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

final class ApFileTypesTest extends TestCase {
	var $options_git = array(
		'repo-owner'			=> null,
		'repo-name'			=> null,
		'github-token'			=> null,
	);

	var $options_auto_approvals = array(
		'commit-test-file-types-1'	=> null,	
		'autoapprove-filetypes'		=> null,
	);

	protected function setUp() {
		vipgoci_unittests_get_config_values(
			'git',
			$this->options_git
		);

		vipgoci_unittests_get_config_values(
			'auto-approvals',
			$this->options_auto_approvals
		);

		$this->options = array_merge(
			$this->options_git,
			$this->options_auto_approvals
		);

		$this->options['token'] =
			$this->options['github-token'];

		unset( $this->options['github-token'] );
	
		$this->options['commit'] =
			$this->options['commit-test-file-types-1'];
	
		$this->options['autoapprove'] = true;
		$this->options['autoapprove-filetypes'] =
			explode(
				',',
				$this->options['autoapprove-filetypes']
			);

		$this->options['branches-ignore'] = array();
	}

	protected function tearDown() {
		$this->options = null;
		$this->options_git = null;
		$this->options_auto_approval = null;
	}

	/**
	 * @covers ::vipgoci_ap_file_types
	 */
	public function testFileTypes1() {
		$auto_approved_files_arr = array();

		ob_start();

		vipgoci_ap_file_types(
			$this->options,
			$auto_approved_files_arr
		);

		ob_end_clean();

		$this->assertEquals(
			$auto_approved_files_arr,
			array(
				'auto-approvable-1.txt' => 'autoapprove-filetypes',
				'auto-approvable-2.txt' => 'autoapprove-filetypes',
				'auto-approvable-3.jpg' => 'autoapprove-filetypes',
			)
		);
	}
}
