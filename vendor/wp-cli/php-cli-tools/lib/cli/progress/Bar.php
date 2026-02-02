<?php
/**
 * PHP Command Line Tools
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 *
 * @author    James Logsdon <dwarf@girsbrain.org>
 * @copyright 2010 James Logsdom (http://girsbrain.org)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace cli\progress;

use cli;
use cli\Notify;
use cli\Progress;
use cli\Shell;
use cli\Streams;

/**
 * Displays a progress bar spanning the entire shell.
 *
 * Basic format:
 *
 *   ^MSG  PER% [=======================            ]  00:00 / 00:00$
 */
class Bar extends Progress {
	protected $_bars = '=>';
	protected $_formatMessage = '{:msg}  {:percent}% [';
	protected $_formatTiming = '] {:elapsed} / {:estimated}';
	protected $_format = '{:msg}{:bar}{:timing}';

	/**
	 * Instantiates a Bar Progress indicator.
	 *
	 * @param string  $msg             The text to display next to the indicator.
	 * @param int     $total           The total number of ticks we will be performing.
	 * @param int     $interval        The interval in milliseconds between updates.
	 * @param string  $formatMessage   Optional format for the message part. Supports placeholders:
	 *                                 {:msg}, {:percent}, {:current}, {:total}
	 * @see cli\Progress::__construct()
	 */
	public function __construct($msg, $total, $interval = 100, $formatMessage = null) {
		parent::__construct($msg, $total, $interval);
		if ($formatMessage !== null) {
			$this->_formatMessage = $formatMessage;
		}
	}

	/**
	 * Prints the progress bar to the screen with percent complete, elapsed time
	 * and estimated total time.
	 *
	 * @param boolean  $finish  `true` if this was called from
	 *                          `cli\Notify::finish()`, `false` otherwise.
	 * @see cli\out()
	 * @see cli\Notify::formatTime()
	 * @see cli\Notify::elapsed()
	 * @see cli\Progress::estimated();
	 * @see cli\Progress::percent()
	 * @see cli\Shell::columns()
	 */
	public function display($finish = false) {
		$_percent = $this->percent();

		$percent = str_pad(floor($_percent * 100), 3);
		$msg = $this->_message;
		$current = $this->current();
		$total = $this->total();
		$msg = Streams::render($this->_formatMessage, compact('msg', 'percent', 'current', 'total'));

		$estimated = $this->formatTime($this->estimated());
		$elapsed   = str_pad($this->formatTime($this->elapsed()), strlen($estimated));
		$timing    = Streams::render($this->_formatTiming, compact('elapsed', 'estimated'));

		$size = Shell::columns();
		$size -= strlen($msg . $timing);
		if ( $size < 0 ) {
			$size = 0;
		}

		$bar = str_repeat($this->_bars[0], floor($_percent * $size)) . $this->_bars[1];
		// substr is needed to trim off the bar cap at 100%
		$bar = substr(str_pad($bar, $size, ' '), 0, $size);

		Streams::out($this->_format, compact('msg', 'bar', 'timing'));
	}

	/**
	 * This method augments the base definition from cli\Notify to optionally
	 * allow passing a new message.
	 *
	 * @param int    $increment The amount to increment by.
	 * @param string $msg       The text to display next to the Notifier. (optional)
	 * @see cli\Notify::tick()
	 */
	public function tick($increment = 1, $msg = null) {
		if ($msg) {
			$this->_message = $msg;
		}
		Notify::tick($increment);
	}
}
