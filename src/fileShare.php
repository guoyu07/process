<?php
namespace phpth\process;

use Exception;

class fileShare
{
    /**
     * 文件每次读取字节数
     * @var int
     */
    protected $len_every_read = 8192;

    /**
     * 文件全路径
     * @var string
     */
    protected $file_path ;

    /**
     * 打开的文件句柄
     * @var
     */
    protected $file_handle ;

    /**
     * FileShare constructor.
     * @param $file_path
     * @param $root_path
     * @throws Exception
     */
    public function __construct($file_path ,$root_path = '/dev/shm/')
    {
        $root_path = rtrim($root_path,'/\\');
        if($file_path)
        {
            $file_path = trim($file_path,' /\\');
            $path_info  = preg_split('#[\\\/]+#m', $file_path);
            foreach($path_info as $k=>$v)
            {
                if($v=='..' || $v == '.')
                {
                    unset($path_info[$k]) ;
                }
            }
            $file_path = join('/',$path_info);
            if(!$file_path)
            {
                goto def;
            }
        }
        else
        {
            def:
            $file_path = 'file.j';
        }

        $this->file_path = "{$root_path}/file/{$file_path}";
        $dir = dirname($this->file_path) ;
        if(!is_dir($dir))
        {
            if(!mkdir($dir,0700,true))
            {
                throw new Exception("无法创建目录：{$dir}");
            }
        }
        if(!file_exists($this->file_path))
        {
            if(!touch($this->file_path))
            {
                throw new Exception("无法创建文件：{$this->file_path}");
            }
        }

        $this->file_handle = fopen($this->file_path, 'c+') ;
        if(!is_resource($this->file_handle))
        {
            throw new Exception('无法打开存储文件');
        }
    }

    /**
     * 设置共享值
     * @param $key
     * @param $value
     * @return bool
     */
    public function set ($key, $value)
    {
        $data = $this->read() ;
        if($data ===false)  return false ;
        $data[$key] = $value ;
        $data = $this->write($data);
        if($data !== false )
        {
            $data = true ;
        }
        return $data ;
    }

    /**
     * 获取值
     * @param $key
     * @param bool $all
     * @return bool|mixed|string
     */
    public function get ($key,$all = false )
    {
        $data = $this->read() ;
        if($data ===false)  return false ;
        if(!$all)
        {
            if(isset($data[$key]))
            {
                $data = $data[$key];

            }
            else
            {
                $data = null ;
            }
        }
        return $data ;
    }

    /**
     * 删除数据
     * @param $key
     * @return bool|mixed|string
     */
    public function del ($key)
    {
        $data = $this->read() ;
        if($data ===false)  return false ;
        if (!isset($data[$key])) {
            $data = true;
            goto end;
        }
        unset($data[$key]);
        $this->write($data);
        $data = true;
        end:
        return $data;
    }

    /**
     * 加锁
     * @param bool $hang
     * @return bool
     */
    public function lock($hang = true )
    {
        return flock($this->file_handle,$hang?LOCK_EX:LOCK_EX|LOCK_NB);
    }

    /**
     * 解锁
     * @return bool
     */
    public function unlock()
    {
        return flock($this->file_handle, LOCK_UN);
    }

    /**
     * 写入数据
     * @param $data
     * @return bool|int
     */
    protected function write($data)
    {
        ftruncate($this->file_handle, 0);
        rewind($this->file_handle);
        $data = json_encode($data);
        $data =  fwrite($this->file_handle, $data) ;
        if($data===false)
        {
            return false ;
        }
        else
        {
            fflush($this->file_handle);
            return $data ;
        }
    }

    /**
     * 读取数据
     * @return bool|mixed|string
     */
    protected function read()
    {
        if(!rewind($this->file_handle)) return false ;
        $data = '';
        while (!feof($this->file_handle))
        {
            $data =$data . fread($this->file_handle, $this->len_every_read);
        }
        $data = trim($data);
        $data = json_decode($data,true);
        if($data===false) return false ;
        if(empty($data)) return [];
        return $data ;
    }

    /**
     * 删除存储
     * @return bool
     */
    public function remove()
    {
        if(flock($this->file_handle, LOCK_EX))
        {
            $res =(boolean) ftruncate($this->file_handle, 0);
            unlink($this->file_path);
            $this->unlock();
            fclose($this->file_handle);
            return $res ;
        }
        else
        {
            return false ;
        }
    }

    /**
     * 释放资源
     */
    public function __destruct()
    {
        if(is_resource($this->file_handle))
        {
            $this->unlock();
            fclose($this->file_handle);
        }
    }
}