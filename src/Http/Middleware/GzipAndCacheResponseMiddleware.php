<?php

namespace Dolphiq\GzipAndCacheResponse\Http\Middleware;

use Closure;
use Illuminate\Contracts\Routing\Middleware;
use Illuminate\Http\Response;
use Dolphiq\GzipAndCacheResponse\GzipAndCacheResponse;

class GzipAndCacheResponseMiddleware 
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
     
    protected $_cache_next_request = false;
    protected $_cache_file_name = '';
    protected $_cache_full_path_name = '';
    protected $_cache_hit = false;
    

    protected $_gzip_and_cache_response;



    public function __construct(GzipAndCacheResponse $_gzip_and_cache_response)
    {
	    
        $this->_gzip_and_cache_response = $_gzip_and_cache_response;
        
    }
  
     
    public function handle($request, Closure $next)
    {
	    

	    // check if the page should be filtered!

		$input = $request->all();
			
	    if($request->format()=='html' && $request->getMethod()=='GET' && count($input)==0) {
			    
		     
			// we also can use; $request->path(); .. 
			
		    $this->_cache_file_name = md5($request->url()).'.gz';
			$this->_cache_full_path_name = storage_path('app/dolphiq/htmlcache/' . $this->_cache_file_name);
			
			// default false
			$this->_cache_hit=false;
			
						
			if(file_exists($this->_cache_full_path_name)) {
				// check file last modification date
				$filetime = \File::lastModified($this->_cache_full_path_name);
				$curtime=date("U");
				$difference = round (($curtime - $filetime));
				//die($difference);
				if($difference>60*60*24) { // max 1 day old
					
					\File::delete($this->_cache_full_path_name);
				} else
				{
					$this->_cache_hit=true;
				}		
			
			}				
			
			if($this->_cache_hit===true) {
			
				// set headers
				header("Html-Cache-Hit: true");
				header("Html-Cache-File: ".$this->_cache_file_name);
				header("Html-Cache-Age: ".$difference);
				
				$encoded_html=\File::get($this->_cache_full_path_name);
				header('Content-Length: ' . strlen($encoded_html));
				header('Content-Encoding: gzip');
				echo $encoded_html;
				
			    flush();
			    ob_flush();
				
				
				// please flush to browser, and re-fetch if needed
				if($difference>10) { // more than a minute old, please re fetch after sent to browser
				
					\File::delete($this->_cache_full_path_name);
					
					// todo, please use other method to fetch url
					file_get_contents($request->fullUrl());
					
					
				}
				
				die();
			} else 
			
			{
				$this->_cache_next_request=true;
					
				$response = $next($request)->header('Html-Cache-Hit', 'false');   
				
				if($this->_cache_next_request==true) {
					$response=$response->header('Html-Cache-Stored', 'true');   
					$response=$response->header('Html-Cache-File', $this->_cache_file_name);   
					// \File::put($this->_cache_full_path_name,$response->content());
					//\File::put($this->_cache_full_path_name,gzencode($response->content(),9));
					
					//$encoded_html = gzencode(Model::all(), 9);
					//header('Content-Length: ' . strlen($encoded_html));
					//header('Content-Encoding: gzip');
				}
				
				return $response;				
			}
			
	    } else  
	    {
		    // just perform the request without a change 
		    return $next($request);
	    }
	  
        
    }  
 
	// save the file in the terminate class (after all other middle ware
    public function terminate($request, $response)
    {
	    
	    $input = $request->all();
	    
        // Store the final response in a file
 	    if($request->format()=='html' && $request->getMethod()=='GET' && count($input)==0) {
			 
		     
			// we also can use; $request->path(); .. 
				    
		    $this->_cache_file_name = md5($request->url()).'.gz';
			$this->_cache_full_path_name = storage_path('app/dolphiq/htmlcache/' . $this->_cache_file_name);		
			{
				$this->_cache_next_request=true;
					
				//$response = $next($request)->header('Html-Cache-Hit', 'false');   
				
				if($this->_cache_next_request==true) {
 
					// \File::put($this->_cache_full_path_name,$response->content());
					\File::put($this->_cache_full_path_name,gzencode($response->content(),9));
					
				}     
			}
			
			
        
        
			} 
 
    }
    
    /**
     * Check if the response is a usable response class.
     *
     * @param mixed $response
     *
     * @return bool
     */
    protected function isAResponseObject($response)
    {
        return is_object($response) && $response instanceof Response;
    }

    /**
     * Check if the content type header is html.
     *
     * @param \Illuminate\Http\Response $response
     *
     * @return bool
     */
    protected function isAnHtmlResponse(Response $response)
    {
        $type = $response->headers->get('Content-Type');

        return strtolower(strtok($type, ';')) === 'text/html';
    }    
    
}
