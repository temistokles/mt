<?php

namespace App\Extensions\Csv;

use App\Extensions\Csv\Exceptions\BeforeProcessException;
use App\Extensions\Csv\Exceptions\InternalException;
use App\Extensions\Csv\Exceptions\WhileProcessException;
use Kdyby\Translation\Translator;
use Nette\Http\FileUpload;
use Nette\Object;
use Nette\Utils\Callback;
use Nette\Utils\Strings;
use Tracy\Debugger;

class Parser extends Object
{

	const CHARSET_IN = "WINDOWS-1250";
	const CHARSET_OUT = "UTF-8";

	/** @var Translator @inject */
	public $translator;

	/** @var FileUpload */
	private $file;

	/** @var Callback */
	private $callback;

	/** @var string */
	private $delimiter;

	/** @var string */
	private $enclosure;

	/** @var string */
	private $escape;

	/** @var int */
	private $lenght;

	/** @var string */
	private $charsetIn = self::CHARSET_IN;

	/** @var string */
	private $charsetOut = self::CHARSET_OUT;

	/** @var resource */
	private $handle;

	/** @var bool */
	private $checkRow = FALSE;

	/** @var array */
	private $rowAliases = [];

	/** @var bool */
	private $skipFirstLine = TRUE;

	public function __construct()
	{
		$this->setCsv();
		$this->setCharset();
	}

	public function setCsv($delimiter = ',', $enclosure = '"', $escape = '\\', $lenght = 0)
	{
		$this->delimiter = (string) $delimiter;
		$this->enclosure = (string) $enclosure;
		$this->escape = (string) $escape;
		$this->lenght = (int) $lenght;
		return $this;
	}

	public function setCharset($in = self::CHARSET_IN, $out = self::CHARSET_OUT)
	{
		$this->charsetIn = (string) $in;
		$this->charsetOut = (string) $out;
		return $this;
	}

	public function setUnSkipFirstLine($value = TRUE)
	{
		$this->skipFirstLine = !$value;
		return $this;
	}

	public function setFile(FileUpload $file)
	{
		if ($file->isOk() && file_exists($file->getTemporaryFile())) {
			$this->file = $file->getTemporaryFile();
		} else {
			throw new BeforeProcessException('Inserted file is corrupted.');
		}
		return $this;
	}

	public function setCallback($callable)
	{
		$this->callback = Callback::closure($callable);
		return $this;
	}

	/**
	 * @param int $aliases number or columns
	 */
	public function setRowChecker(array $aliases)
	{
		$this->checkRow = TRUE;
		$this->rowAliases = $aliases;
		return $this;
	}

	public function execute()
	{
		if ($this->callback === NULL) {
			throw new InternalException('Use method \'setCallback()\' before \'execute()\'.');
		}

		$this->openCsv();
		$executed = [];

		$line = 0;
		while (($row = fgetcsv($this->handle, $this->lenght, $this->delimiter, $this->enclosure, $this->escape))) {
			$line++;
			if ($this->skipFirstLine && $line === 1) {
				continue;
			}
			try {
				$executed[$line] = $this->parseLine($line, $row);
			} catch (InternalException $e) {
				throw new WhileProcessException($executed, $e->getMessage());
			} catch (\Exception $e) {
				Debugger::log($e->getMessage(), Strings::webalize(get_class($this)));
				$message = $this->translator->translate('Proccessing failed on line %count%.', $line);
				throw new WhileProcessException($executed, $message);
			}
		}

		$this->closeCsv();
		return $executed;
	}

	/*	 * ***************************************************** */

	private function parseLine($lineNumber, array $row)
	{
		$this->checkRow($row, $lineNumber);
		$convertedRow = [];
		foreach ($row as $key => $item) {
			$convertedRow[$key] = $this->convert($item, $key);
		}
		call_user_func($this->callback, $convertedRow);
	}

	private function checkRow(array $row, $lineNumber)
	{
		if ($this->checkRow) {
			$actualCount = count($row);
			$expectedCount = count($this->rowAliases);
			if ($actualCount !== $expectedCount) {
				$text = 'Line #%line% failed validation scheme'
						. ' - should have %expected% columns'
						. ' and it have %actual%.';
				$message = $this->translator->translate($text, [
					'line' => $lineNumber,
					'expected' => $expectedCount,
					'actual' => $actualCount
				]);
				throw new InternalException($message);
			}
		}
		return TRUE;
	}

	private function openCsv()
	{
		if ($this->file === NULL) {
			throw new InternalException('Use method \'setFile()\' before \'execute()\'.');
		}

		if (($handle = fopen($this->file, "r")) !== FALSE) {
			$this->handle = $handle;
		} else {
			throw new BeforeProcessException('Can not open the file.');
		}
	}

	private function closeCsv()
	{
		if ($this->handle !== NULL) {
			fclose($this->handle);
		}
	}

	public function convert($value, &$key)
	{
		if (is_array($this->rowAliases) && array_key_exists($key, $this->rowAliases)) {
			$key = $this->rowAliases[$key];
		}
		$decoded = $value === "" ? NULL : iconv($this->charsetIn, $this->charsetOut, $value);
		return $decoded;
	}

}

interface IParserFactory
{

	/** @return Parser */
	public function create();
}
