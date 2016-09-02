<?php
namespace hapn\console;

use hapn\util\Logger;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/9/2 13:44
 * @copyright : 2016 jiehun.com.cn
 * @filesource: Command.php
 */
class Command
{
    private $cmd;
    private $outputFile;
    private $errorFile;
    private $outputCallback;
    private $errorCallback;
    private $inputFile;
    private $cwd;
    private $envs = [];

    public $outputs = [];
    public $errors = [];

    public function __construct(string $cmd, ...$args)
    {
        if (empty($args)) {
            $this->cmd = $cmd;
        } else {
            array_unshift($args, $cmd);
            $this->cmd = call_user_func_array('sprintf', $args);
        }
    }

    /**
     * Set current directory
     * @param string $cwd
     * @return $this
     */
    public function setCwd(string $cwd)
    {
        $this->cmd = $cwd;
        return $this;
    }


    /**
     * Set environment variables
     * @param array $envs
     * @return $this
     */
    public function setEnvs(array $envs)
    {
        foreach ($envs as $k => $v) {
            $this->envs[$k] = $v;
        }
        return $this;
    }

    /**
     * Set input' file
     * @param $file
     * @return $this
     */
    public function setInputFile($file)
    {
        $this->inputFile = $file;
        return $this;
    }


    /**
     * Set standard output's file
     * @param $file
     * @param $mode
     * @return $this
     */
    public function setOutputFile(string $file, $mode = 'w')
    {
        $this->outputFile = [$file, $mode];
        return $this;
    }

    /**
     * Set error output's file
     * @param $file
     * @param $mode
     * @return $this
     */
    public function setErrorFile(string $file, $mode = 'w')
    {
        $this->errorFile = [$file, $mode];
        return $this;
    }

    /**
     * Set error output's callback
     * @param callable $callback
     * @return $this
     */
    public function setErrorCallback(callable $callback)
    {
        $this->errorCallback = $callback;
        return $this;
    }

    /**
     * Set standard output's callback
     * @param callable $callback
     * @return $this
     */
    public function setOutputCallback(callable $callback)
    {
        $this->outputCallback = $callback;
        return $this;
    }

    /**
     * Execute command
     */
    public function execute()
    {
        if (isset($GLOBALS['__HapN_appid'])) {
            $GLOBALS['__HapN_appid']++;
        }

        $start = microtime(true);
        Logger::trace("command start:" . $this->cmd);

        $descs = [];
        if ($this->inputFile) {
            $descs[0] = ['file', $this->inputFile];
        } else {
            $descs[0] = ['pipe', 'r'];
        }

        if ($this->outputFile) {
            $descs[1] = ['file', $this->outputFile[0], $this->outputFile[1]];
        } else {
            $descs[1] = ['pipe', 'w'];
        }

        if ($this->errorFile) {
            $descs[2] = ['file', $this->errorFile[0], $this->errorFile[1]];
        } else {
            $descs[2] = ['pipe', 'w'];
        }

        $ptr = proc_open($this->cmd, $descs, $pipes, $this->cwd, $this->envs);
        if (!is_resource($ptr)) {
            return false;
        }

        $readOutput = true;
        $readError = true;

        if (isset($pipes[1])) {
            stream_set_blocking($pipes[1], 0);
        } else {
            $readOutput = false;
        }
        if (isset($pipes[2])) {
            stream_set_blocking($pipes[2], 0);
        } else {
            $readError = false;
        }

        while ($readOutput || $readError) {
            if ($readOutput) {
                if (feof($pipes[1])) {
                    fclose($pipes[1]);
                    $readOutput = false;
                } else {
                    $line = fgets($pipes[1], 1024);
                    if ($line !== false) {
                        $line = trim($line, PHP_EOL);
                        $this->outputs[] = $line;
                        if ($this->outputCallback) {
                            call_user_func($this->outputCallback, $line);
                        }
                    }
                }
            }

            if ($readError) {
                if (feof($pipes[2])) {
                    fclose($pipes[2]);
                    $readError = false;
                } else {
                    $line = fgets($pipes[2], 1024);
                    if ($line !== false) {
                        $line = trim($line, PHP_EOL);
                        $this->errors[] = $line;
                        if ($this->errorCallback) {
                            call_user_func($this->errorCallback, $line);
                        }
                    }
                }
            }
        }

        $exitCode = proc_close($ptr);
        $cost = (microtime(true) - $start) * 1000;
        Logger::trace("command end, cost:%s", $cost);
        return $exitCode;
    }

    /**
     * Alias  for [ new Command($cmd, ...$args)->execute()]
     * @param $cmd
     * @param array ...$args
     * @return int exitCode
     */
    public static function run($cmd, ...$args)
    {
        $command = (new \ReflectionClass(__CLASS__))
            ->newInstanceArgs(func_get_args());
        return $command->execute();
    }
}