<?php
class Library_Log
{
    private $_logFile;
    public function __construct($fileName, $truncate = false)
    {
        $this->_logFile = $fileName;
        @mkdir(dirname($this->_logFile), 0766, true);
        #if (is_file($this->_logFile) && $truncate) {
        #    file_put_contents($this->_logFile, '');
        #}
    }
    public function writeLog($data)
    {
        file_put_contents($this->_logFile,
                          date('H:i:s') . ' ' . $data . chr(10),
                          FILE_APPEND);
    }
}
