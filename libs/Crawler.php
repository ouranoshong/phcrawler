<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午9:08
 */

namespace PhCrawler;

use Exception;
//use PDO;
use PhCrawler\Enums\AbortReasons;
use PhCrawler\Enums\MultiProcessModes;
use PhCrawler\Enums\UrlCacheTypes;
use PhCrawler\ProcessCommunication\DocumentInfoQueue;
use PhCrawler\ProcessCommunication\ProcessHandler;
use PhCrawler\ProcessCommunication\StatusHandler;
use PhCrawler\UrlCache\Base as UrlCacheBase;
use PhCrawler\CookieCache\SQLite as SQLiteCookieCache;
use PhCrawler\CookieCache\Memory as MemoryCookieCache;
use PhCrawler\UrlCache\SQLite as SQLiteUrlCache;
use PhCrawler\UrlCache\Memory as MemoryUrlCache;
use PhCrawler\CookieCache\Base as CookieCacheBase;
use PhCrawler\Utils\Utils;

/**
 * Class Crawler
 *
 * @package PhCrawler
 */
class Crawler
{
    /**
     * @var string
     */
    public $class_version = "0.83rc1";

    /**
     * The HTTPRequest-Object
     *
     * @var Request
     */
    protected $PageRequest;

    /**
     * The PHPCrawlerLinkCache-Object
     *
     * @var UrlCacheBase
     */
    protected $LinkCache;

    /**
     * The PHPCrawlerCookieCache-Object
     *
     * @var  CookieCacheBase
     */
    protected $CookieCache;

    /**
     * The UrlFilter-Object
     *
     * @var URLFilter
     */
    protected $UrlFilter;

    /**
     * The RobotsTxtParser-Object
     *
     * @var RobotsTxtParser
     */
    protected $RobotsTxtParser;

    /**
     * UserSendDataCahce-object.
     *
     * @var UserSendDataCache
     */
    protected $UserSendDataCache;

    /**
     * The URL the crawler should start with.
     *
     * The URL is full qualified and normalized.
     *
     * @var string
     */
    protected $starting_url = "";

    /**
     * Defines whether robots.txt-file should be obeyed
     *
     * @val bool
     */
    protected $obey_robots_txt = false;

    /**
     * Location of robots.txt-file to obey as URI
     *
     * @val string
     */
    protected $robots_txt_uri;

    /**
     * Limit of requests to preform
     *
     * @var int
     */
    protected $request_limit = 0;

    /**
     * Limit of bytes to receive
     *
     * @var int The limit in bytes
     */
    protected $traffic_limit = 0;

    /**
     * Defines if only documents that were received will be counted.
     *
     * @var bool
     */
    protected $only_count_received_documents = true;

    /**
     * Flag cookie-handling enabled/diabled
     *
     * @var bool
     */
    protected $cookie_handling_enabled = true;

    /**
     * The reason why the process was aborted/finished.
     *
     * @var int One of the AbortReasons::ABORTREASON-constants.
     */
    protected $porcess_abort_reason = null;

    /**
     * Flag indicating whether this instance is running in a child-process (if crawler runs multi-processed)
     */
    protected $is_chlid_process = false;

    /**
     * Flag indicating whether this instance is running in the parent-process (if crawler runs multi-processed)
     */
    protected $is_parent_process = false;

    /**
     * URl cache-type.
     *
     * @var int One of the UrlCacheTypes::URLCACHE..-constants.
     */
    protected $url_cache_type = 1;

    /**
     * UID of this instance of the crawler
     *
     * @var string
     */
    protected $crawler_uniqid = null;

    /**
     * Base-directory for temporary directories
     *
     * @var string
     */
    protected $working_base_directory;

    /**
     * Complete path to the temporary directory
     *
     * @var string
     */
    protected $working_directory = null;

    /**
     * @var array
     */
    protected $link_priority_array = array();

    /**
     * Number of child-process (NOT the PID!)
     *
     * @var int
     */
    protected $child_process_number = null;

    /**
     * @var int
     */
    protected $child_process_count = 1;

    /**
     * PHPCrawlerProcessCommunication-object
     *
     * @var ProcessHandler
     */
    protected $ProcessHandler = null;

    /**
     * PHPCrawlerStatusHandler-object
     *
     * @var StatusHandler
     */
    protected $CrawlerStatusHandler = null;

    /**
     * Multiprocess-mode the crawler is runnung in.
     *
     * @var int One of the MultiProcessModes-constants
     */
    protected $multiprocess_mode = 0;

    /**
     * DocumentInfoQueue-object
     *
     * @var DocumentInfoQueue
     */
    protected $DocumentInfoQueue = null;

    /**
     * @var bool
     */
    protected $follow_redirects_till_content = true;

    /**
     * Flag indicating whether resumtion is activated
     *
     * @var DocumentInfoQueue
     */
    protected $resumtion_enabled = false;

    /**
     * Request-delay-time
     *
     * @var float
     */
    protected $request_delay_time = null;

    /**
     * Flag indicating whether the URL-cahce was purged at the beginning of a crawling-process
     */
    protected $urlcache_purged = false;


    /**
     * @var  Status
     */
    public $crawlerStatus;

    /**
     * @var array
     */
    public $child_pids;

    /**
     * Crawler constructor.
     */
    public function __construct()
    {
        // Create uniqid for this crawlerinstance
        $this->crawler_uniqid = getmypid().time();

        $this->PageRequest = new Request();
        $this->PageRequest->setHeaderCheckCallbackFunction($this, "handleHeaderInfo");

    
        $this->UrlFilter = new URLFilter();
        $this->RobotsTxtParser = new RobotsTxtParser();
        $this->UserSendDataCache = new UserSendDataCache();

        // Set default temp-dir
        $this->working_base_directory = Utils::getSystemTempDir();
    }

    /**
     * Initiates a crawler-process
     */
    protected function initCrawlerProcess()
    {
        // Create working directory
        $this->createWorkingDirectory();

        // Setup url-cache
        if ($this->url_cache_type == UrlCacheTypes::SQLITE)
            $this->LinkCache = new SQLiteUrlCache($this->working_directory."urlcache.db3", true);
        else
            $this->LinkCache = new MemoryUrlCache();

        // Perge/cleanup SQLite-urlcache for resumed crawling-processes (only ONCE!)
        if ($this->url_cache_type == UrlCacheTypes::SQLITE && $this->urlcache_purged == false)
        {
            $this->LinkCache->purgeCache();
            $this->urlcache_purged = true;
        }

        // Setup cookie-cache (use SQLite-cache if crawler runs multi-processed)
        if ($this->url_cache_type == UrlCacheTypes::SQLITE)
            $this->CookieCache = new SQLiteCookieCache($this->working_directory."cookiecache.db3", true);
        else $this->CookieCache = new MemoryCookieCache();

        // ProcessHandler
        $this->ProcessHandler = new ProcessHandler($this->crawler_uniqid, $this->working_directory);

        // Setup PHPCrawlerStatusHandler
        $this->CrawlerStatusHandler = new StatusHandler($this->crawler_uniqid, $this->working_directory);
        $this->setupCrawlerStatusHandler();

        // DocumentInfo-Queue
        if ($this->multiprocess_mode == MultiProcessModes::PARENT_EXECUTES_USER_CODE)
            $this->DocumentInfoQueue = new DocumentInfoQueue($this->working_directory."doc_queue.db3", true);

        // Set tmp-file for PageRequest
        $this->PageRequest->setTmpFile($this->working_directory."phcrawl_".getmypid().".tmp");

        // Pass url-priorities to link-cache
        $this->LinkCache->addLinkPriorities($this->link_priority_array);

        // Pass base-URL to the UrlFilter
        $this->UrlFilter->setBaseURL($this->starting_url);

        // Add the starting-URL to the url-cache with link-depth 0
        $url_descriptor = new URLDescriptor($this->starting_url);
        $url_descriptor->url_link_depth = 0;
        $this->LinkCache->addUrl($url_descriptor);
    }

    /**
     * Starts the crawling process in single-process-mode.
     *
     * Be sure you did override the {@link handleDocumentInfo()}- or {@link handlePageData()}-method before calling the go()-method
     * to process the documents the crawler finds.
     *
     * @section 1 Basic settings
     */
    public function go()
    {
        // Process robots.txt
        if ($this->obey_robots_txt == true)
            $this->processRobotsTxt();

        $this->startChildProcessLoop();
    }


    /**
     * @param int $process_count
     * @param int $multiprocess_mode
     *
     * @throws \Exception
     */
    public function goMultiProcessed($process_count = 3, $multiprocess_mode = 1)
    {
        $this->multiprocess_mode = $multiprocess_mode;
        $this->child_process_count = $process_count;

        // Check if fork is supported
        if (!function_exists("pcntl_fork"))
        {
            throw new Exception("PHPCrawl running with multi processes not supported in this PHP-environment (function pcntl_fork() missing).".
                "Try running from command-line (cli) and/or installing the PHP PCNTL-extension.");
        }

        if (!function_exists("sem_get"))
        {
            throw new Exception("PHPCrawl running with multi processes not supported in this PHP-environment (function sem_get() missing).".
                "Try installing the PHP SEMAPHORE-extension.");
        }

        if (!function_exists("posix_kill"))
        {
            throw new Exception("PHPCrawl running with multi processes not supported in this PHP-environment (function posix_kill() missing).".
                "Try installing the PHP POSIX-extension.");
        }

        if (!class_exists("PDO"))
        {
            throw new Exception("PHPCrawl running with multi processes not supported in this PHP-environment (class PDO missing).".
                "Try installing the PHP PDO-extension.");
        }

        Benchmark::start("crawling_process");

        // Set url-cache-type to sqlite.
        $this->url_cache_type = UrlCacheTypes::SQLITE;

        // Init process
        $this->initCrawlerProcess();

        // Process robots.txt
        if ($this->obey_robots_txt == true)
            $this->processRobotsTxt();

        // Fork off child-processes
        $pids = array();

        for($i=1; $i<=$process_count; $i++)
        {
            $pids[$i] = pcntl_fork();

            if(!$pids[$i])
            {
                // Childprocess goes here
                $this->is_chlid_process = true;
                $this->child_process_number = $i;
                $this->ProcessHandler->registerChildPID(getmypid());
                $this->startChildProcessLoop();
            }
        }

        // Set flag "parent-process"
        $this->is_parent_process = true;

        // Determinate all child-PIDs
        $this->child_pids = $this->ProcessHandler->getChildPIDs($process_count);

        // If crawler runs in MPMODE_PARENT_EXECUTES_USERCODE-mode -> start controller-loop
        if ($this->multiprocess_mode == MultiProcessModes::PARENT_EXECUTES_USER_CODE)
        {
            $this->startControllerProcessLoop();
        }

        // Wait for childs to finish
        for ($i=1; $i<=$process_count; $i++)
        {
            pcntl_waitpid($pids[$i], $status, WUNTRACED);
        }

        // Get crawler-status (needed for process-report)
        $this->crawlerStatus = $this->CrawlerStatusHandler->getCrawlerStatus();

        // Cleanup crawler
        $this->cleanup();

        Benchmark::stop("crawling_process");
    }

    /**
     * Starts the loop of the controller-process (main-process).
     */
    protected function startControllerProcessLoop()
    {
        // If multiprocess-mode is not MPMODE_PARENT_EXECUTES_USERCODE -> exit process
        if ($this->multiprocess_mode != MultiProcessModes::PARENT_EXECUTES_USER_CODE) exit;

        $this->initCrawlerProcess();
        $this->initChildProcess();

        while (true)
        {
            // Check for abort
            if ($this->checkForAbort() !== null)
            {
                $this->ProcessHandler->killChildProcesses();
                break;
            }

            // Get next DocInfo-object from queue
            $DocInfo = $this->DocumentInfoQueue->getNextDocumentInfo();

            if ($DocInfo == null)
            {
                // If there are nor more links in cache AND there are no more DocInfo-objects in queue -> passedthrough
                if ($this->LinkCache->containsURLs() == false && $this->DocumentInfoQueue->getDocumentInfoCount() == 0)
                {
                    $this->CrawlerStatusHandler->updateCrawlerStatus(null, AbortReasons::PASSED_THROUGH);
                }

                usleep(100000);
                continue;
            }

            // Update crawler-status
            $this->CrawlerStatusHandler->updateCrawlerStatus($DocInfo);

            // Call the "abstract" method handlePageData
            $user_abort = false;

            // If defined by user -> call old handlePageData-method, otherwise don't (because of high memory-usage)
            if (method_exists($this, "handlePageData"))
            {
                $page_info = $DocInfo->toArray();
                $user_return_value = $this->handlePageData($page_info);
                if ($user_return_value < 0) $user_abort = true;
            }

            // Call the "abstract" method handleDocumentInfo
            $user_return_value = $this->handleDocumentInfo($DocInfo);
            if ($user_return_value < 0) $user_abort = true;

            // Update status if user aborted process
            if ($user_abort == true)
                $this->CrawlerStatusHandler->updateCrawlerStatus(null, AbortReasons::USER_ABORT);
        }
    }

    /**
     * Starts the loop of a child-process.
     */
    protected function startChildProcessLoop()
    {
        $this->initCrawlerProcess();

        // Call overidable method initChildProcess()
        $this->initChildProcess();

        // Start benchmark (if single-processed)
        if ($this->is_chlid_process == false)
        {
            Benchmark::start("crawling_process");
        }

        // Init vars
        $stop_crawling = false;

        // Main-Loop
        while ($stop_crawling == false)
        {
            // Get next URL from cache
            $UrlDescriptor = $this->LinkCache->getNextUrl();

            // Process URL
            if ($UrlDescriptor != null)
            {
                $stop_crawling = $this->processUrl($UrlDescriptor);
            }
            else
            {
                usleep(500000);
            }

            if ($this->multiprocess_mode != MultiProcessModes::PARENT_EXECUTES_USER_CODE)
            {
                // If there's nothing more to do
                if ($this->LinkCache->containsURLs() == false)
                {
                    $stop_crawling = true;
                    $this->CrawlerStatusHandler->updateCrawlerStatus(null, AbortReasons::PASSED_THROUGH);
                }

                // Check for abort form other processes
                if ($this->checkForAbort() !== null) $stop_crawling = true;
            }
        }

        // Loop enden gere. If child-process -> kill it
        if ($this->is_chlid_process == true)
        {
            if ($this->multiprocess_mode == MultiProcessModes::PARENT_EXECUTES_USER_CODE) return;
            else exit;
        }

        $this->crawlerStatus = $this->CrawlerStatusHandler->getCrawlerStatus();

        // Cleanup crawler
        $this->cleanup();

        // Stop benchmark (if single-processed)
        if ($this->is_chlid_process == false)
        {
            Benchmark::stop("crawling_process");
        }
    }

    /**
     * Receives and processes the given URL
     *
     * @param URLDescriptor $UrlDescriptor The URL as URLDescriptor-object
     * @return bool TURE if the crawling-process should be aborted after processig the URL, otherwise FALSE.
     */
    protected function processUrl(URLDescriptor $UrlDescriptor)
    {
        // Check for abortion from other processes first if mode is MPMODE_CHILDS_EXECUTES_USERCODE
        if ($this->multiprocess_mode == MultiProcessModes::CHILDS_EXECUTES_USER_CODE)
        {
            // Check for abortion (any limit reached?)
            if ($this->checkForAbort() !== null) return true;
        }

        Benchmark::start("processing_url");

        // Setup HTTP-request
        $this->PageRequest->setUrl($UrlDescriptor);

        // Add cookies to request
        if ($this->cookie_handling_enabled == true)
            $this->PageRequest->addCookieDescriptors($this->CookieCache->getCookiesForUrl($UrlDescriptor->url_rebuild));

        // Add basic-authentications to request
        $authentication = $this->UserSendDataCache->getBasicAuthenticationForUrl($UrlDescriptor->url_rebuild);
        if ($authentication != null)
        {
            $this->PageRequest->setBasicAuthentication($authentication["username"], $authentication["password"]);
        }

        // Add post-data to request
        $post_data = $this->UserSendDataCache->getPostDataForUrl($UrlDescriptor->url_rebuild);
        while (list($post_key, $post_value) = @each($post_data))
        {
            $this->PageRequest->addPostData($post_key, $post_value);
        }

        // Do request
        $this->delayRequest();
        $PageInfo = $this->PageRequest->fetch();

        // Remove post and cookie-data from request-object
        $this->PageRequest->clearCookies();
        $this->PageRequest->clearPostData();

        // Complete PageInfo-Object with benchmarks
        Benchmark::stop("processing_url");
        $PageInfo->benchmarks = Benchmark::getAllBenchmarks();

        // Call user-methods, update craler-status and check for abortion here if crawler doesn't run in MPMODE_PARENT_EXECUTES_USERCODE
        if ($this->multiprocess_mode != MultiProcessModes::PARENT_EXECUTES_USER_CODE)
        {
            // Check for abortion (any limit reached?)
            if ($this->checkForAbort() !== null) return true;

            // Update crawler-status
            $this->CrawlerStatusHandler->updateCrawlerStatus($PageInfo);

            $user_abort = false;

            // If defined by user -> call old handlePageData-method, otherwise don't (because of high memory-usage)
            if (method_exists($this, "handlePageData"))
            {
                $page_info = $PageInfo->toArray();
                $user_return_value = $this->handlePageData($page_info);
                if ($user_return_value < 0) $user_abort = true;
            }

            // Call the "abstract" method handleDocumentInfo
            $user_return_value = $this->handleDocumentInfo($PageInfo);
            if ($user_return_value < 0) $user_abort = true;

            // Update status if user aborted process
            if ($user_abort == true)
            {
                $this->CrawlerStatusHandler->updateCrawlerStatus(null, AbortReasons::USER_ABORT);
            }

            // Check for abortion again (any limit reached?)
            if ($this->checkForAbort() !== null) return true;
        }

        // Add document to the DocumentInfoQueue if mode is MPMODE_PARENT_EXECUTES_USERCODE
        if ($this->multiprocess_mode == MultiProcessModes::PARENT_EXECUTES_USER_CODE)
        {
            $this->DocumentInfoQueue->addDocumentInfo($PageInfo);
        }

        // Filter found URLs by defined rules
        if ($this->follow_redirects_till_content == true)
        {
            $crawler_status = $this->CrawlerStatusHandler->getCrawlerStatus();

            // If content wasn't found so far and content was found NOW
            if ($crawler_status->first_content_url == null && $PageInfo->http_status_code == 200)
            {
                $this->CrawlerStatusHandler->updateCrawlerStatus(null, null, $PageInfo->url);
                $this->UrlFilter->setBaseURL($PageInfo->url); // Set current page as base-URL
                $this->UrlFilter->filterUrls($PageInfo);
                $this->follow_redirects_till_content = false; // Content was found, so this can be set to FALSE
            }
            else if ($crawler_status->first_content_url == null)
            {
                $this->UrlFilter->keepRedirectUrls($PageInfo, true); // Content wasn't found so far, so just keep redirect-urls and
                // decrease lindepth
            }
            else if ($crawler_status->first_content_url != null)
            {
                $this->follow_redirects_till_content = false;
                $this->UrlFilter->filterUrls($PageInfo);
            }
        }
        else
        {
            $this->UrlFilter->filterUrls($PageInfo);
        }

        // Add Cookies to Cookie-cache
        if ($this->cookie_handling_enabled == true) $this->CookieCache->addCookies($PageInfo->cookies);

        // Add filtered links to URL-cache
        $this->LinkCache->addURLs($PageInfo->links_found_url_descriptors);

        // Mark URL as "followed"
        $this->LinkCache->markUrlAsFollowed($UrlDescriptor);

        Benchmark::resetAll(array("crawling_process"));

        return false;
    }

    /**
     *
     */
    protected function processRobotsTxt()
    {
        Benchmark::start("processing_robots_txt");

        $robotstxt_rules = $this->RobotsTxtParser->parseRobotsTxt(new URLDescriptor($this->starting_url),
            $this->PageRequest->userAgentString,
            $this->robots_txt_uri);
        $this->UrlFilter->addURLFilterRules($robotstxt_rules);

        Benchmark::stop("processing_robots_txt");
    }

    /**
     * Checks if the crawling-process should be aborted.
     *
     * @return int NULL if the process shouldn't be aborted yet, otherwise one of the AbortReasons::ABORTREASON-constants.
     */
    protected function checkForAbort()
    {
        Benchmark::start("checkning_for_abort");

        $abort_reason = null;

        // Get current status
        $crawler_status = $this->CrawlerStatusHandler->getCrawlerStatus();

        // if crawlerstatus already marked for ABORT
        if ($crawler_status->abort_reason !== null)
        {
            $abort_reason = $crawler_status->abort_reason;
        }

        // Check for reached limits

        // If traffic-limit is reached
        if ($this->traffic_limit > 0 && $crawler_status->bytes_received >= $this->traffic_limit)
            $abort_reason = AbortReasons::TRAFFIC_LIMIT_REACHED;

        // If request-limit is set
        if ($this->request_limit > 0)
        {
            // If document-limit regards to received documetns
            if ($this->only_count_received_documents == true && $crawler_status->documents_received >= $this->request_limit)
            {
                $abort_reason = AbortReasons::FILE_LIMIT_REACHED;
            }
            elseif ($this->only_count_received_documents == false && $crawler_status->links_followed >= $this->request_limit)
            {
                $abort_reason = AbortReasons::FILE_LIMIT_REACHED;
            }
        }

        $this->CrawlerStatusHandler->updateCrawlerStatus(null, $abort_reason);

        Benchmark::stop("checkning_for_abort");

        return $abort_reason;
    }

    /**
     * Delays the execution of the next request depending on the setRequestDelayTime()-setting and updates
     * the last-request-time afterwards
     */
    protected function delayRequest()
    {
        // Delay next request only if a request-delay was set
        if ($this->request_delay_time != null)
        {
            while (true)
            {
                $crawler_status = $this->CrawlerStatusHandler->getCrawlerStatus();

                // Wait if the time of the last request isn't way back enough
                if ($crawler_status->last_request_time + $this->request_delay_time > Benchmark::getmicrotime())
                    usleep($this->request_delay_time * 1000000 / 2);
                else
                    break;
            }

            // Update last-request-time
            $this->CrawlerStatusHandler->updateCrawlerStatus(null, null, null, Benchmark::getmicrotime());
        }
    }

    /**
     * Setups the CrawlerStatusHandler dependent on the crawler-settings
     */
    protected function setupCrawlerStatusHandler()
    {
        // Cases the crawlerstatus has to be written to file
        if ($this->multiprocess_mode == MultiProcessModes::CHILDS_EXECUTES_USER_CODE || $this->resumtion_enabled == true)
        {
            $this->CrawlerStatusHandler->write_status_to_file = true;
        }

        if ($this->request_delay_time != null && $this->multiprocess_mode != MultiProcessModes::NONE)
        {
            $this->CrawlerStatusHandler->write_status_to_file = true;
        }

        // Cases a crawlerstatus-update has to be locked
        if ($this->multiprocess_mode == MultiProcessModes::CHILDS_EXECUTES_USER_CODE)
        {
            $this->CrawlerStatusHandler->lock_status_updates = true;
        }

        if ($this->request_delay_time != null && $this->multiprocess_mode != MultiProcessModes::NONE)
        {
            $this->CrawlerStatusHandler->lock_status_updates = true;
        }
    }

    /**
     * Creates the working-directory for this instance of the cralwer.
     */
    protected function createWorkingDirectory()
    {
        $this->working_directory = $this->working_base_directory."phcrawler_tmp_".$this->crawler_uniqid.DIRECTORY_SEPARATOR;

        // Check if writable
        if (!is_writeable($this->working_base_directory))
        {
            throw new Exception("Error creating working directory '".$this->working_directory."'");
        }

        // Create dir
        if (!file_exists($this->working_directory))
        {
            mkdir($this->working_directory);
        }
    }

    /**
     * Cleans up the crawler after it has finished.
     */
    protected function cleanup()
    {
        // Free/unlock caches
        $this->CookieCache->cleanup();
        $this->LinkCache->cleanup();

        // Delete working-dir
        Utils::rmDir($this->working_directory);

        // Remove semaphore (if multiprocess-mode)
        if ($this->multiprocess_mode != MultiProcessModes::NONE)
        {
            $sem_key = sem_get($this->crawler_uniqid);
            sem_remove($sem_key);
        }
    }

    /**
     * Retruns summarizing report-information about the crawling-process after it has finished.
     *
     * @return ProcessReport ProcessReport-object containing process-summary-information
     * @section 1 Basic settings
     */
    public function getProcessReport()
    {
        // Get current crawler-Status
        $CrawlerStatus = $this->crawlerStatus;

        // Create report
        $Report = new ProcessReport();

        $Report->links_followed = $CrawlerStatus->links_followed;
        $Report->files_received = $CrawlerStatus->documents_received;
        $Report->bytes_received = $CrawlerStatus->bytes_received;
        $Report->process_runtime = Benchmark::getElapsedTime("crawling_process");

        if ($Report->process_runtime > 0)
            $Report->data_throughput = $Report->bytes_received / $Report->process_runtime;

        // Process abort-reason
        $Report->abort_reason = $CrawlerStatus->abort_reason;

        if ($CrawlerStatus->abort_reason == AbortReasons::TRAFFIC_LIMIT_REACHED)
            $Report->traffic_limit_reached = true;

        if ($CrawlerStatus->abort_reason == AbortReasons::FILE_LIMIT_REACHED)
            $Report->file_limit_reached = true;

        if ($CrawlerStatus->abort_reason == AbortReasons::USER_ABORT)
            $Report->user_abort = true;

        // Peak memory-usage
        if (function_exists("memory_get_peak_usage"))
            $Report->memory_peak_usage = memory_get_peak_usage(true);

        // Benchmark: Average server connect time
        if ($CrawlerStatus->sum_server_connects > 0)
            $Report->avg_server_connect_time = $CrawlerStatus->sum_server_connect_time / $CrawlerStatus->sum_server_connects;

        // Benchmark: Average server response time
        if ($CrawlerStatus->sum_server_responses > 0)
            $Report->avg_server_response_time = $CrawlerStatus->sum_server_response_time / $CrawlerStatus->sum_server_responses;

        // Average data tranfer time
        if ($CrawlerStatus->sum_data_transfer_time > 0)
            $Report->avg_proc_data_transfer_rate = $CrawlerStatus->unbuffered_bytes_read / $CrawlerStatus->sum_data_transfer_time;

        return $Report;
    }


    /**
     * @param \PhCrawler\ResponseHeader $header
     *
     * @return int
     */
    public function handleHeaderInfo(ResponseHeader $header)
    {
        return 1;
    }


    public function initChildProcess()
    {
    }

    /**
     * Override this method to get access to all information about a page or file the crawler found and received.
     *
     * Everytime the crawler found and received a document on it's way this method will be called.
     * The crawler passes all information about the currently received page or file to this method
     * by a DocumentInfo-object.
     *
     * Please see the {@link DocumentInfo} documentation for a list of all properties describing the
     * html-document.
     *
     * Example:
     * <code>
     * class MyCrawler extends PHPCrawler
     * {
     *   function handleDocumentInfo($PageInfo)
     *   {
     *     // Print the URL of the document
     *     echo "URL: ".$PageInfo->url."<br />";
     *
     *     // Print the http-status-code
     *     echo "HTTP-statuscode: ".$PageInfo->http_status_code."<br />";
     *
     *     // Print the number of found links in this document
     *     echo "Links found: ".count($PageInfo->links_found_url_descriptors)."<br />";
     *
     *     // ..
     *   }
     * }
     * </code>
     *
     * @param DocumentInfo $PageInfo A DocumentInfo-object containing all information about the currently received document.
     *                                         Please see the reference of the {@link DocumentInfo}-class for detailed information.
     * @return int                             The crawling-process will stop immedeatly if you let this method return any negative value.
     *
     * @section 3 Overridable methods / User data-processing
     */
    public function handleDocumentInfo(DocumentInfo $PageInfo){}

    /**
     * Sets the URL of the first page the crawler should crawl (root-page).
     *
     * The given url may contain the protocol (http://www.foo.com or https://www.foo.com), the port (http://www.foo.com:4500/index.php)
     * and/or basic-authentication-data (http://loginname:passwd@www.foo.com)
     *
     * This url has to be set before calling the {@link go()}-method (of course)!
     * If this root-page doesn't contain any further links, the crawling-process will stop immediately.
     *
     * @param string $url The URL
     * @return bool
     *
     * @section 1 Basic settings
     */
    public function setURL($url)
    {
        $url = trim($url);

        if ($url != "" && is_string($url))
        {
            $this->starting_url = Utils::normalizeURL($url);
            return true;
        }
        else return false;
    }

    /**
     * Sets the port to connect to for crawling the starting-url set in setUrl().
     *
     * The default port is 80.
     *
     * Note:
     * <code>
     * $cralwer->setURL("http://www.foo.com");
     * $crawler->setPort(443);
     * </code>
     * effects the same as
     *
     * <code>
     * $cralwer->setURL("http://www.foo.com:443");
     * </code>
     *
     * @param int $port The port
     * @return bool
     * @section 1 Basic settings
     */
    public function setPort($port)
    {
        // Check port
        if (!preg_match("#^[0-9]{1,5}$#", $port)) return false;

        // Add port to the starting-URL
        $url_parts = Utils::splitURL($this->starting_url);
        $url_parts["port"] = $port;
        $this->starting_url = Utils::buildURLFromParts($url_parts, true);

        return true;
    }

    /**
     * Adds a regular expression togehter with a priority-level to the list of rules that decide what links should be prefered.
     *
     * Links/URLs that match an expression with a high priority-level will be followed before links with a lower level.
     * All links that don't match with any of the given rules will get the level 0 (lowest level) automatically.
     *
     * The level can be any positive integer.
     *
     * <b>Example:</b>
     *
     * Telling the crawler to follow links that contain the string "forum" before links that contain ".gif" before all other found links.
     * <code>
     * $crawler->addLinkPriority("/forum/", 10);
     * $cralwer->addLinkPriority("/\.gif/", 5);
     * </code>
     *
     * @param string $regex  Regular expression definig the rule
     * @param int    $level  The priority-level
     *
     * @return bool  TRUE if a valid preg-pattern is given as argument and was succsessfully added, otherwise it returns FALSE.
     * @section 10 Other settings
     */
    function addLinkPriority($regex, $level)
    {
        $check = Utils::checkRegexPattern($regex); // Check pattern
        if ($check == true && preg_match("/^[0-9]*$/", $level))
        {
            $c = count($this->link_priority_array);
            $this->link_priority_array[$c]["match"] = trim($regex);
            $this->link_priority_array[$c]["level"] = trim($level);

            return true;
        }
        else return false;
    }

    /**
     * Defines whether the crawler should follow redirects sent with headers by a webserver or not.
     *
     * @param bool $mode  If TRUE, the crawler will follow header-redirects.
     *                    The default-value is TRUE.
     * @return bool
     * @section 10 Other settings
     */
    public function setFollowRedirects($mode)
    {
        return $this->PageRequest->setFindRedirectURLs($mode);
    }

    /**
     * Defines whether the crawler should follow HTTP-redirects until first content was found, regardless of defined filter-rules and follow-modes.
     *
     * Sometimes, when requesting an URL, the first thing the webserver does is sending a redirect to
     * another location, and sometimes the server of this new location is sending a redirect again
     * (and so on).
     * So at least its possible that you find the expected content on a totally different host
     * as expected.
     *
     * If you set this option to TRUE, the crawler will follow all these redirects until it finds some content.
     * If content finally was found, the root-url of the crawling-process will be set to this url and all
     * defined options (folllow-mode, filter-rules etc.) will relate to it from now on.
     *
     * @param bool $mode If TRUE, the crawler will follow redirects until content was finally found.
     *                   Defaults to TRUE.
     * @section 10 Other settings
     */
    public function setFollowRedirectsTillContent($mode)
    {
        $this->follow_redirects_till_content = $mode;
    }

    /**
     * Sets the basic follow-mode of the crawler.
     *
     * The following list explains the supported follow-modes:
     *
     * <b>0 - The crawler will follow EVERY link, even if the link leads to a different host or domain.</b>
     * If you choose this mode, you really should set a limit to the crawling-process (see limit-options),
     * otherwise the crawler maybe will crawl the whole WWW!
     *
     * <b>1 - The crawler only follow links that lead to the same domain like the one in the root-url.</b>
     * E.g. if the root-url (setURL()) is "http://www.foo.com", the crawler will follow links to "http://www.foo.com/..."
     * and "http://bar.foo.com/...", but not to "http://www.another-domain.com/...".
     *
     * <b>2 - The crawler will only follow links that lead to the same host like the one in the root-url.</b>
     * E.g. if the root-url (setURL()) is "http://www.foo.com", the crawler will ONLY follow links to "http://www.foo.com/...", but not
     * to "http://bar.foo.com/..." and "http://www.another-domain.com/...". <b>This is the default mode.</b>
     *
     * <b>3 - The crawler only follows links to pages or files located in or under the same path like the one of the root-url.</b>
     * E.g. if the root-url is "http://www.foo.com/bar/index.html", the crawler will follow links to "http://www.foo.com/bar/page.html" and
     * "http://www.foo.com/bar/path/index.html", but not links to "http://www.foo.com/page.html".
     *
     * @param int $follow_mode The basic follow-mode for the crawling-process (0, 1, 2 or 3).
     * @return bool
     *
     * @section 1 Basic settings
     */
    public function setFollowMode($follow_mode)
    {
        // Check mode
        if (!preg_match("/^[0-3]{1}$/", $follow_mode)) return false;

        $this->UrlFilter->general_follow_mode = $follow_mode;
        return true;
    }

    /**
     * Adds a rule to the list of rules that decides which pages or files - regarding their content-type - should be received
     *
     * After receiving the HTTP-header of a followed URL, the crawler check's - based on the given rules - whether the content of that URL
     * should be received.
     * If no rule matches with the content-type of the document, the content won't be received.
     *
     * Example:
     * <code>
     * $crawler->addContentTypeReceiveRule("#text/html#");
     * $crawler->addContentTypeReceiveRule("#text/css#");
     * </code>
     * This rules lets the crawler receive the content/source of pages with the Content-Type "text/html" AND "text/css".
     * Other pages or files with different content-types (e.g. "image/gif") won't be received (if this is the only rule added to the list).
     *
     * <b>IMPORTANT:</b> By default, if no rule was added to the list, the crawler receives every content.
     *
     * Note: To reduce the traffic the crawler will cause, you only should add content-types of pages/files you really want to receive.
     * But at least you should add the content-type "text/html" to this list, otherwise the crawler can't find any links.
     *
     * @param string $regex The rule as a regular-expression
     * @return bool TRUE if the rule was added to the list.
     *              FALSE if the given regex is not valid.
     * @section 2 Filter-settings
     */
    public function addContentTypeReceiveRule($regex)
    {
        return $this->PageRequest->addReceiveContentType($regex);
    }


    /**
     * @param $regex
     *
     * @return bool
     */
    public function addReceiveContentType($regex)
    {
        return $this->addContentTypeReceiveRule($regex);
    }

    /**
     * Adds a rule to the list of rules that decide which URLs found on a page should be followd explicitly.
     *
     * If the crawler finds an URL and this URL doesn't match with any of the given regular-expressions, the crawler
     * will ignore this URL and won't follow it.
     *
     * NOTE: By default and if no rule was added to this list, the crawler will NOT filter ANY URLs, every URL the crawler finds
     * will be followed (except the ones "excluded" by other options of course).
     *
     * Example:
     * <code>
     * $crawler->addURLFollowRule("#(htm|html)$# i");
     * $crawler->addURLFollowRule("#(php|php3|php4|php5)$# i");
     * </code>
     * These rules let the crawler ONLY follow URLs/links that end with "html", "htm", "php", "php3" etc.
     *
     * @param string $regex Regular-expression defining the rule
     * @return bool TRUE if the regex is valid and the rule was added to the list, otherwise FALSE.
     *
     * @section 2 Filter-settings
     */
    public function addURLFollowRule($regex)
    {
        return $this->UrlFilter->addURLFollowRule($regex);
    }

    /**
     * Adds a rule to the list of rules that decide which URLs found on a page should be ignored by the crawler.
     *
     * If the crawler finds an URL and this URL matches with one of the given regular-expressions, the crawler
     * will ignore this URL and won't follow it.
     *
     * Example:
     * <code>
     * $crawler->addURLFilterRule("#(jpg|jpeg|gif|png|bmp)$# i");
     * $crawler->addURLFilterRule("#(css|js)$# i");
     * </code>
     * These rules let the crawler ignore URLs that end with "jpg", "jpeg", "gif", ..., "css"  and "js".
     *
     * @param string $regex Regular-expression defining the rule
     * @return bool TRUE if the regex is valid and the rule was added to the list, otherwise FALSE.
     *
     * @section 2 Filter-settings
     */
    public function addURLFilterRule($regex)
    {
        return $this->UrlFilter->addURLFilterRule($regex);
    }

    /**
     * Alias for addURLFollowRule().
     *
     * @section 11 Deprecated
     * @deprecated
     *
     */
    public function addFollowMatch($regex)
    {
        return $this->addURLFollowRule($regex);
    }

    /**
     * @param $regex
     *
     * @return bool
     */
    public function addNonFollowMatch($regex)
    {
        return $this->addURLFilterRule($regex);
    }

    /**
     * Adds a rule to the list of rules that decides what types of content should be streamed diretly to a temporary file.
     *
     * If a content-type of a page or file matches with one of these rules, the content will be streamed directly into a
     * temporary file without claiming local RAM.
     *
     * It's recommendend to add all content-types of files that may be of bigger size to prevent memory-overflows.
     * By default the crawler will receive every content to memory!
     *
     * The content/source of pages and files that were streamed to file are not accessible directly within the overidden method
     * {@link handleDocumentInfo()}, instead you get information about the file the content was stored in.
     * (see properties {@link DocumentInfo::received_to_file} and {@link DocumentInfo::content_tmp_file}).
     *
     * Please note that this setting doesn't effect the link-finding results, also file-streams will be checked for links.
     *
     * A common setup may look like this example:
     * <code>
     * // Basically let the crawler receive every content (default-setting)
     * $crawler->addReceiveContentType("##");
     *
     * // Tell the crawler to stream everything but "text/html"-documents to a tmp-file
     * $crawler->addStreamToFileContentType("#^((?!text/html).)*$#");
     * </code>
     *
     * @param string $regex The rule as a regular-expression
     * @return bool         TRUE if the rule was added to the list and the regex is valid.
     * @section 10 Other settings
     */
    public function addStreamToFileContentType($regex)
    {
        return $this->PageRequest->addStreamToFileContentType($regex);
    }
    

    /**
     * Defines whether the crawler should parse and obey robots.txt-files.
     *
     * If this is set to TRUE, the crawler looks for a robots.txt-file for the root-URL of the crawling-process at the default location
     * and - if present - parses it and obeys all containig directives appliying to the
     * useragent-identification of the cralwer ("PHPCrawl" by default or manually set by calling {@link setUserAgentString()})
     *
     * The default-value is FALSE (for compatibility reasons).
     *
     * Pleas note that the directives found in a robots.txt-file have a higher priority than other settings made by the user.
     * If e.g. {@link addFollowMatch}("#http://foo\.com/path/file\.html#") was set, but a directive in the robots.txt-file of the host
     * foo.com says "Disallow: /path/", the URL http://foo.com/path/file.html will be ignored by the crawler anyway.
     *
     * @param bool   $mode           Set to TRUE if you want the crawler to obey robots.txt-files.
     * @param string $robots_txt_uri Optionally. The URL or path to the robots.txt-file to obey as URI (like "http://mysite.com/path/myrobots.txt"
    or "file://../a_robots_file.txt")
     *                               If not set (or set to null), the crawler uses the default robots.txt-location of the root-URL ("http://rooturl.com/robots.txt")
     *
     * @return bool
     * @section 2 Filter-settings
     */
    public function obeyRobotsTxt($mode, $robots_txt_uri = null)
    {
        if (!is_bool($mode)) return false;

        $this->obey_robots_txt = $mode;

        if ($mode == true)
            $this->robots_txt_uri = $robots_txt_uri;
        else
            $this->robots_txt_uri = null;

        return true;
    }

    /**
     * @param $regex
     *
     * @return bool
     */
    public function addReceiveToTmpFileMatch($regex)
    {
        return $this->addStreamToFileContentType($regex);
    }


    /**
     * @param $regex
     *
     * @return bool
     */
    public function addReceiveToMemoryMatch($regex)
    {
        return true;
    }

    /**
     * Sets a limit to the total number of requests the crawler should execute.
     *
     * If the given limit is reached, the crawler stops the crawling-process. The default-value is 0 (no limit).
     *
     * If the second parameter is set to true, the given limit refers to to total number of successfully received documents
     * instead of the number of requests.
     *
     * @param int $limit                          The limit, set to 0 for no limit (default value).
     * @param bool $only_count_received_documents OPTIONAL.
     *                                            If TRUE, the given limit refers to the total number of successfully received documents.
     *                                            If FALSE, the given limit refers to the total number of requests done, regardless of the number of successfully received documents.
     *                                            Defaults to FALSE.
     * @return bool
     * @section 5 Limit-settings
     */
    public function setRequestLimit($limit, $only_count_received_documents = false)
    {
        if (!preg_match("/^[0-9]*$/", $limit)) return false;

        $this->request_limit = $limit;
        $this->only_count_received_documents = $only_count_received_documents;
        return true;
    }

    /**
     * Alias for setRequestLimit() method.
     *
     * @section 11 Deprecated
     * @deprecated Please use setRequestLimit() method!
     */
    public function setPageLimit($limit, $only_count_received_documents = false)
    {
        return $this->setRequestLimit($limit, $only_count_received_documents);
    }

    /**
     * Sets the content-size-limit for content the crawler should receive from documents.
     *
     * If the crawler is receiving the content of a page or file and the contentsize-limit is reached, the crawler stops receiving content
     * from this page or file.
     *
     * Please note that the crawler can only find links in the received portion of a document.
     *
     * The default-value is 0 (no limit).
     *
     * @param int $bytes The limit in bytes.
     * @return bool
     * @section 5 Limit-settings
     */
    public function setContentSizeLimit($bytes)
    {
        return $this->PageRequest->setContentSizeLimit($bytes);
    }


    public function setTrafficLimit($bytes, $complete_requested_files = true)
    {
        if (preg_match("#^[0-9]*$#", $bytes))
        {
            $this->traffic_limit = $bytes;
            return true;
        }
        else return false;
    }

    /**
     * Enables or disables cookie-handling.
     *
     * If cookie-handling is set to TRUE, the crawler will handle all cookies sent by webservers just like a common browser does.
     * The default-value is TRUE.
     *
     * It's strongly recommended to set or leave the cookie-handling enabled!
     *
     * @param bool $mode
     * @return bool
     * @section 10 Other settings
     */
    public function enableCookieHandling($mode)
    {
        if (!is_bool($mode)) return false;

        $this->cookie_handling_enabled = $mode;
        return true;
    }

    /**
     * Alias for enableCookieHandling()
     *
     * @section 11 Deprecated
     * @deprecated Please use enableCookieHandling()
     */
    public function setCookieHandling($mode)
    {
        return $this->enableCookieHandling($mode);
    }

    /**
     * Enables or disables agressive link-searching.
     *
     * If this is set to FALSE, the crawler tries to find links only inside html-tags (< and >).
     * If this is set to TRUE, the crawler tries to find links everywhere in an html-page, even outside of html-tags.
     * The default value is TRUE.
     *
     * Please note that if agressive-link-searching is enabled, it happens that the crawler will find links that are not meant as links and it also happens that it
     * finds links in script-parts of pages that can't be rebuild correctly - since there is no javascript-parser/interpreter implemented.
     * (E.g. javascript-code like document.location.href= a_var + ".html").
     *
     * Disabling agressive-link-searchingn results in a better crawling-performance.
     *
     * @param bool $mode
     * @return bool
     * @section 6 Linkfinding settings
     */
    public function enableAggressiveLinkSearch($mode)
    {
        return $this->PageRequest->enableAggressiveLinkSearch($mode);
    }

    /**
     * Alias for enableAggressiveLinkSearch()
     *
     * @section 11 Deprecated
     * @deprecated Please use enableAggressiveLinkSearch()
     */
    public function setAggressiveLinkExtraction($mode)
    {
        return $this->enableAggressiveLinkSearch($mode);
    }

    public function setLinkExtractionTags($tag_array)
    {
        return $this->PageRequest->setLinkExtractionTags($tag_array);
    }

    public function addLinkExtractionTags()
    {
        $tags = func_get_args();
        return $this->setLinkExtractionTags($tags);
    }


    public function addBasicAuthentication($url_regex, $username, $password)
    {
        return $this->UserSendDataCache->addBasicAuthentication($url_regex, $username, $password);
    }


    public function setUserAgentString($user_agent)
    {
        $this->PageRequest->userAgentString = $user_agent;
        return true;
    }


    public function disableExtendedLinkInfo($mode)
    {
    }

    /**
     * Sets the working-directory the crawler should use for storing temporary data.
     *
     * Every instance of the crawler needs and creates a temporary directory for storing some
     * internal data.
     *
     * This setting defines which base-directory the crawler will use to store the temporary
     * directories in. By default, the crawler uses the systems temp-directory as working-directory.
     * (i.e. "/tmp/" on linux-systems)
     *
     * All temporary directories created in the working-directory will be deleted automatically
     * after a crawling-process has finished.
     *
     * NOTE: To speed up the performance of a crawling-process (especially when using the
     * SQLite-urlcache), try to set a mounted shared-memory device as working-direcotry
     * (i.e. "/dev/shm/" on Debian/Ubuntu-systems).
     *
     * Example:
     * <code>
     * $crawler->setWorkingDirectory("/tmp/");
     * </code>
     *
     * @param string $directory The working-directory
     * @return bool             TRUE on success, otherwise false.
     * @section 1 Basic settings
     */
    public function setWorkingDirectory($directory)
    {
        if (is_writeable($this->working_base_directory))
        {
            $this->working_base_directory = $directory;
            return true;
        }
        else return false;
    }

    /**
     * Assigns a proxy-server the crawler should use for all HTTP-Requests.
     *
     * @param string $proxy_host     Hostname or IP of the proxy-server
     * @param int    $proxy_port     Port of the proxy-server
     * @param string $proxy_username Optional. The username for proxy-authentication or NULL if no authentication is required.
     * @param string $proxy_password Optional. The password for proxy-authentication or NULL if no authentication is required.
     *
     * @section 10 Other settings
     */
    public function setProxy($proxy_host, $proxy_port, $proxy_username = null, $proxy_password = null)
    {
        $this->PageRequest->setProxy($proxy_host, $proxy_port, $proxy_username, $proxy_password);
    }

    /**
     * Sets the timeout in seconds for connection tries to hosting webservers.
     *
     * If the the connection to a host can't be established within the given time, the
     * request will be aborted.
     *
     * @param int $timeout The timeout in seconds, the default-value is 5 seconds.
     * @return bool
     *
     * @section 10 Other settings
     */
    public function setConnectionTimeout($timeout)
    {
        if (preg_match("#[0-9]+#", $timeout))
        {
            $this->PageRequest->socketConnectTimeout = $timeout;
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Sets the timeout in seconds for waiting for data on an established server-connection.
     *
     * If the connection to a server was be etablished but the server doesnt't send data anymore without
     * closing the connection, the crawler will wait the time given in timeout and then close the connection.
     *
     * @param int $timeout The timeout in seconds, the default-value is 2 seconds.
     * @return bool
     *
     * @section 10 Other settings
     */
    public function setStreamTimeout($timeout)
    {
        if (preg_match("#[0-9]+#", $timeout))
        {
            $this->PageRequest->socketReadTimeout = $timeout;
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Adds a rule to the list of rules that decide in what kind of documents the crawler
     * should search for links in (regarding their content-type)
     *
     * By default the crawler ONLY searches for links in documents of type "text/html".
     * Use this method to add one or more other content-types the crawler should check for links.
     *
     * Example:
     * <code>
     * $crawler->addLinkSearchContentType("#text/css# i");
     * $crawler->addLinkSearchContentType("#text/xml# i");
     * </code>
     * These rules let the crawler search for links in HTML-, CSS- ans XML-documents.
     *
     * <b>Please note:</b> It is NOT recommended to let the crawler checkfor links in EVERY document-
     * type! This could slow down the crawling-process dramatically (e.g. if the crawler receives large
     * binary-files like images and tries to find links in them).
     *
     * @param string $regex Regular-expression defining the rule
     * @return bool         TRUE if the rule was successfully added
     *
     * @section 6 Linkfinding settings
     */
    public function addLinkSearchContentType($regex)
    {
        return $this->PageRequest->addLinkSearchContentType($regex);
    }

    /**
     * Defines what type of cache will be internally used for caching URLs.
     *
     * Currently phpcrawl is able to use a in-memory-cache or a SQlite-database-cache for
     * caching/storing found URLs internally.
     *
     * The memory-cache ({@link UrlCacheTypes}::URLCACHE_MEMORY) is recommended for spidering small to medium websites.
     * It provides better performance, but the php-memory-limit may be hit when too many URLs get added to the cache.
     * This is the default-setting.
     *
     * The SQlite-cache ({@link UrlCacheTypes}::URLCACHE_SQLite) is recommended for spidering huge websites.
     * URLs get cached in a SQLite-database-file, so the cache only is limited by available harddisk-space.
     * To increase performance of the SQLite-cache you may set it's location to a shared-memory device like "/dev/shm/"
     * by using the {@link setWorkingDirectory()}-method.
     *
     * Example:
     * <code>
     * $crawler->setUrlCacheType(UrlCacheTypes::URLCACHE_SQLITE);
     * $crawler->setWorkingDirectory("/dev/shm/");
     * </code>
     *
     * <b>NOTE:</b> When using phpcrawl in multi-process-mode ({@link goMultiProcessed()}), the cache-type is automatically set
     * to UrlCacheTypes::URLCACHE_SQLITE.
     *
     * @param int $url_cache_type 1 -> in-memory-cache (default setting)
     *                            2 -> SQlite-database-cache
     *
     *                            Or one of the {@link UrlCacheTypes}::URLCACHE..-constants.
     * @return bool
     * @section 1 Basic settings
     */
    public function setUrlCacheType($url_cache_type)
    {
        if (preg_match("#[1-2]#", $url_cache_type))
        {
            $this->url_cache_type = $url_cache_type;
            return true;
        }
        else return false;
    }

    /**
     * Decides whether the crawler should obey "nofollow"-tags
     *
     * If set to TRUE, the crawler will not follow links that a marked with rel="nofollow"
     * (like &lt;a href="page.html" rel="nofollow"&gt;) nor links from pages containing the meta-tag
     * <meta name="robots" content="nofollow">.
     *
     * By default, the crawler will NOT obey nofollow-tags.
     *
     * @param bool $mode If set to TRUE, the crawler will obey "nofollow"-tags
     * @section 2 Filter-settings
     */
    public function obeyNoFollowTags($mode)
    {
        $this->UrlFilter->obey_nofollow_tags = $mode;
    }

    /**
     * Adds post-data together with an URL-rule to the list of post-data to send with requests.
     *
     * Example
     * <code>
     * $post_data = array("username" => "me", "password" => "my_password", "action" => "do_login");
     * $crawler->addPostData("#http://www\.foo\.com/login.php#", $post_data);
     * </code>
     * This example sends the post-values "username=me", "password=my_password" and "action=do_login" to the URL
     * http://www.foo.com/login.php
     *
     * @param string $url_regex       Regular expression defining the URL(s) the post-data should be send to.
     * @param array  $post_data_array Post-data-array, the array-keys are the post-data-keys, the array-values the post-values.
     *                                (like array("post_key1" => "post_value1", "post_key2" => "post_value2")
     *
     * @return bool
     * @section 10 Other settings
     */
    public function addPostData($url_regex, $post_data_array)
    {
        return $this->UserSendDataCache->addPostData($url_regex, $post_data_array);
    }

    /**
     * Returns the unique ID of the instance of the crawler
     *
     * @return int
     * @section 9 Process resumption
     */
    public function getCrawlerId()
    {
        return $this->crawler_uniqid;
    }


    public function resume($crawler_id)
    {
        if ($this->resumtion_enabled == false)
            throw new Exception("Resumption was not enalbled, call enableResumption() before calling the resume()-method!");

        // Adobt crawler-id
        $this->crawler_uniqid = $crawler_id;

        if (!file_exists($this->working_base_directory."phpcrawl_tmp_".$this->crawler_uniqid.DIRECTORY_SEPARATOR))
        {
            throw new Exception("Couldn't find any previous aborted crawling-process with crawler-id '".$this->crawler_uniqid."'");
        }

        $this->createWorkingDirectory();

        // Unlinks pids file in working-dir (because all PIDs will change in new process)
        if (file_exists($this->working_directory."pids")) unlink($this->working_directory."pids");
    }

    /**
     * Prepares the crawler for process-resumption.
     *
     * In order to be able to resume an aborted/terminated crawling-process, it is necessary to
     * initially call the enableResumption() method in your script/project.
     *
     * For further details on how to resume aborted processes please see the documentation of the
     * {@link resume()} method.
     * @section 9 Process resumption
     */
    public function enableResumption()
    {
        $this->resumtion_enabled = true;
        $this->setUrlCacheType(UrlCacheTypes::SQLITE);
    }

    /**
     * Sets the HTTP protocol version the crawler should use for requests
     *
     * Example:
     * <code>
     * // Lets the crawler use HTTP 1.1 requests
     * $crawler->setHTTPProtocolVersion(PHPCrawlerHTTPProtocols::HTTP_1_1);
     * </code>
     *
     * Since phpcrawl 0.82, HTTP 1.1 is the default protocol.
     *
     * @param int $http_protocol_version One of the {@link PHPCrawlerHTTPProtocols}-constants, or
     *                                   1 -> HTTP 1.0
     *                                   2 -> HTTP 1.1 (default)
     * @return bool
     * @section 1 Basic settings
     */
    public function setHTTPProtocolVersion($http_protocol_version)
    {
        return $this->PageRequest->setHTTPProtocolVersion($http_protocol_version);
    }

    /**
     * Enables support/requests for gzip-encoded content.
     *
     * If set to TRUE, the crawler will request gzip-encoded content from webservers.
     * This will result in reduced data traffic while crawling websites, but the CPU load
     * will rise because the encoded content has to be decoded locally.
     *
     * By default, gzip-requests are disabled for compatibility reasons to earlier versions of phpcrawl.
     *
     * Please note: If gzip-requests are disabled, but a webserver returns gzip-encoded content nevertheless,
     * the crawler will handle the encoded data correctly regardless of this setting.
     *
     * @param bool $mode Set to TRUE for enabling support/requests for gzip-encoded content, defaults to FALSE
     * @section 10 Other settings
     */
    public function requestGzipContent($mode)
    {
        return $this->PageRequest->requestGzipContent($mode);
    }

    /**
     * Sets a delay for every HTTP-requests the crawler executes.
     *
     * The crawler will wait for the given time after every request it does, regardless of
     * the mode it runs in (single-/multiprocessmode).
     *
     * Example 1:
     * <code>
     * // Let's the crawler wait for a half second before every request.
     * $crawler->setRequestDelay(0.5);
     * </code>
     * Example 2:
     * <code>
     * // Limit the request-rate to 100 requests per minute
     * $crawler->setRequestDelay(60/100);
     * </code>
     *
     * @param float $time The request-delay-time in seconds.
     * @return bool
     * @section 5 Limit-settings
     */
    public function setRequestDelay($time)
    {
        if (is_float($time) || is_int($time))
        {
            $this->request_delay_time = $time;
            return true;
        }

        return false;
    }


    public function excludeLinkSearchDocumentSections($document_sections)
    {
        return $this->PageRequest->excludeLinkSearchDocumentSections($document_sections);
    }


    public function setCrawlingDepthLimit($depth)
    {
        if (is_int($depth) && $depth >= 0)
        {
            $this->UrlFilter->max_crawling_depth = $depth;
            return true;
        }

        return false;
    }
}
