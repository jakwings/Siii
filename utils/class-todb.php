<?php
/******************************************************************************\
 * @Version:    0.9.11
 * @Name:       TextOfDatabase
 * @Date:       2014-07-22 08:06:04 +08:00
 * @File:       todb.class.php
 * @Author:     Jak Wings
 * @License:    <https://github.com/jakwings/TextOfDatabase/blob/master/LICENSE>
 * @Compatible: PHP/5.3.2+,5.4.x,5.5.x
 * @Thanks to:  pjjTextBase <http://pjj.pl/pjjtextbase/>
 *              txtSQL <http://txtsql.sourceforge.net>
\******************************************************************************/


/**
* @info   A plain text database manager.
*/
class Todb
{
  const VERSION = '0.9.10';
  /**
  * @info   Database directory
  * @type   string
  */
  private $_db_path = NULL;
  /**
  * @info   Connected to a database?
  * @type   bool
  */
  private $_is_connected = FALSE;
  /**
  * @info   Show errors?
  * @type   bool
  */
  private $_debug = FALSE;
  private $_error_reporting_level = NULL;
  /**
  * @info   Cache of read tables
  * @type   array
  */
  private $_cache = array();
  /**
  * @info   working tables
  * @type   array
  */
  private $_tables = array();

  /**
  * @info   Constructor
  * @return {Todb}
  */
  public function __construct()
  {
    // store original value
    $this->_error_reporting_level = @error_reporting();
  }

  /****************************************************************************\
   * @Public Methods:   Debug, IsConnected, Connect, Disconnect, ListTables,
   *                    CreateTable, DropTable, GetHeaders, Count, Max, Min,
   *                    Unique, Select, Insert, Merge, SetRecords, Append,
   *                    SetHeaders, Update, EmptyCache
  \****************************************************************************/

  /**
  * @info   Open debug mode?
  * @param  {Boolean} $on: whether to show error message
  * @return void
  */
  public function Debug($on)
  {
    $on = !!$on;
    $this->_debug = $on;
    if ( $on ) {
      @error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
    } else {
      if ( !is_null($this->_error_reporting_level) ) {
        @error_reporting($this->_error_reporting_level);
      }
    }
  }
  /**
  * @info   Is connected to the database?
  * @param  void
  * @return {Boolean}
  */
  public function IsConnected()
  {
    return $this->_is_connected;
  }
  /**
  * @info   Connect to the database
  * @param  {String}  $path: (optional) database
  * @return void
  */
  public function Connect($path = './db')
  {
    if ( $this->_is_connected ) {
      $this->_Error('OPERATION_ERROR', 'Not disconnected from previous database');
    }
    if ( FALSE === ($realpath = realpath(dirname($path . '/.')))
      or !is_dir($realpath) )
    {
      $this->_Error('FILE_ERROR', 'Database not found');
    }
    $this->_db_path = $realpath;
    $this->_is_connected = TRUE;
  }
  /**
  * @info   Disconnect from the database
  * @param  void
  * @return void
  */
  public function Disconnect()
  {
    $this->_NeedConnected();
    $this->_cache = array();
    $this->_tables = array();
    $this->_is_connected = FALSE;
    unset($this->_db_path);
  }
  /**
  * @info   Return names of all table in the database if $tname isn't {String}
  *         or to see if table $tname exists
  * @param  {String}  $tname: (optional) table name to find
  * @return {Mixed}
  */
  public function ListTables($tname = NULL)
  {
    $this->_NeedConnected();
    $tables = array();
    if ( FALSE === ($dh = opendir($this->_db_path)) ) {
      $this->_Error('FILE_ERROR', 'Database not found');
    }
    while ( FALSE !== ($fname = readdir($dh)) ) {
      if ( is_file($this->_db_path . '/' . $fname) ) {
        $info = pathinfo($fname);
        if ( 'col' === $info['extension']
          and is_file($this->_db_path . '/' . $info['filename'] . '.row') )
        {
          $tables[] = $info['filename'];
        }
      }
    }
    closedir($dh);
    if ( !is_null($tname) ) {
      $this->_NeedValidName($tname);
      return in_array($tname, $tables, TRUE);
    }
    return $tables;
  }
  /**
  * @info   Create table
  * @param  {String}  $tname: name of table
  * @param  {Array}   $headers: names of headers
  * @return void
  */
  public function CreateTable($tname, $headers)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    if ( $this->ListTables($tname) ) {
      $this->_Error('OPERATION_ERROR', 'Table already exists');
    }
    $tdata = array(
      'headers' => $headers,
      'records' => array()
    );
    $this->_NeedValidTable($tdata);
    $this->_FormatHeaders($tdata, TRUE);
    $this->_WriteTable($tname, $tdata, FALSE, FALSE);
  }
  /**
  * @info   Delete table
  * @param  {String}  $tname: name of table
  * @return void
  */
  public function DropTable($tname)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    $this->_UnlinkTable($tname);
  }
  /**
  * @info   Get headers of specified table
  * @param  {String}  $tname: name of specified table
  * @param  {Boolean} $fromFile: (optional) from the file or the working table
  * @return {Array}
  */
  public function GetHeaders($tname, $fromFile = FALSE)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    if ( !$fromFile ) {
      $this->_NeedFragmentLoaded($tname, TRUE);
      return array_keys($this->_tables[$tname . '.col']);
    }
    if ( FALSE === $this->_ReadHeaders($tname) ) {
      $this->_Error('FILE_ERROR', 'Table not found or broken');
    }
    return array_keys($this->_cache[$tname . '.col']);
  }
  /**
  * @info   Get the number of records of specified table
  *         Alias for simple use of Select()
  * @param  {String}  $tname: name of specified table
  * @param  {Closure} $where: (optional) a function that filter records
  * @param  {Boolean} $fromFile: (optional) from the file or the working table
  * @return {Integer}
  */
  public function Count($tname, $where = NULL, $fromFile = FALSE)
  {
    // positions of $fromFile and $where can be swapped
    // if there are only two arguments
    if ( func_num_args() === 2 and is_bool($where) ) {
      $fromFile = $where;
      $where = NULL;
    }
    return $this->Select($tname, array(
      'action' => 'NUM',
      'where' => $where
    ), $fromFile);
  }
  /**
  * @info   Get the maximal value(s) of column(s) of records of specified table
  *         Alias for simple use of Select()
  *         Return NULL or array of NULLs if no value found
  * @param  {String}  $tname: name of specified table
  * @param  {String}  $column: (optional) specified header of a record
  *         {Array}   $column: (optional) specified headers of a record
  * @param  {Boolean} $fromFile: (optional) from the file or the working table
  * @return {Mixed}
  */
  public function Max($tname, $column = NULL, $fromFile = FALSE)
  {
    // positions of $fromFile and $column can be swapped
    // if there are only two arguments
    if ( func_num_args() === 2 and is_bool($column) ) {
      $fromFile = $column;
      $column = NULL;
    }
    return $this->Select($tname, array(
      'action' => 'MAX',
      'column' => $column
    ), $fromFile);
  }
  /**
  * @info   Get the minimal value(s) of column(s) of records of specified table
  *         Alias for simple use of Select()
  *         Return NULL or array of NULLs if no value found
  * @param  {String}  $tname: name of specified table
  * @param  {String}  $column: (optional) specified header of a record
  *         {Array}   $column: (optional) specified headers of a record
  * @param  {Boolean} $fromFile: (optional) from the file or the working table
  * @return {Mixed}
  */
  public function Min($tname, $column = NULL, $fromFile = FALSE)
  {
    // positions of $fromFile and $column can be swapped
    // if there are only two arguments
    if ( func_num_args() === 2 and is_bool($column) ) {
      $fromFile = $column;
      $column = NULL;
    }
    return $this->Select($tname, array(
      'action' => 'MIN',
      'column' => $column
    ), $fromFile);
  }
  /**
  * @info   Get the unique values of column(s) of records of specified table
  *         Alias for simple use of Select()
  *         Return array of or array of array
  * @param  {String}  $tname: name of specified table
  * @param  {String}  $column: (optional) specified header of a record
  *         {Array}   $column: (optional) specified headers of a record
  * @param  {Boolean} $fromFile: (optional) from the file or the working table
  * @return {Array}
  */
  public function Unique($tname, $column = NULL, $fromFile = FALSE)
  {
    // positions of $fromFile and $column can be swapped
    // if there are only two arguments
    if ( func_num_args() === 2 and is_bool($column) ) {
      $fromFile = $column;
      $column = NULL;
    }
    return $this->Select($tname, array(
      'action' => 'UNI',
      'column' => $column
    ), $fromFile);
  }
  /**
  * @info   Get data of specified table that satisfies conditions
  * @param  {String}  $tname: name of specified table
  * @param  {Array}   $select: (optional) selection information
  *         -- by default, all select info are optional --
  *         'action'  => {str} [ 'GET', 'NUM', 'MAX', 'MIN', 'SET', 'DEL'
  *                              'UNI', 'SET+', 'DEL+' ]
  *                      [default] GET
  *         'range'   => {arr} slice records before processing
  *                      [default] NULL i.e. process all records
  *         'where'   => {func} deal with every record (with referrence)
  *                      [default] NULL i.e. not easy to explain it
  *         'column'  => {str|arr} which column(s) of records to return
  *                      [default] NULL i.e. all columns
  *         'key'     => {str} use column value instead of number index
  *                      [default] NULL i.e. do nothing
  *         'order'   => {arr} sort records by columns
  *                      [default] NULL i.e. do nothing
  *         -- please mind your sever memory, do not deal with big data --
  * @param  {Boolean} $fromFile: (optional) from the file or the working table
  * @return {Mixed}
  */
  public function Select($tname, $select = NULL, $fromFile = FALSE)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    // positions of $fromFile and $select can be swapped
    // if there are only two arguments
    if ( func_num_args() === 2 and is_bool($select) ) {
      $fromFile = $select;
      $select = NULL;
    }
    $this->_FormatSelectInfo($tname, $select, $fromFile);
    if ( !$fromFile ) {
      $this->_NeedFragmentLoaded($tname, FALSE);
      if ( is_null($select) ) {
        return $this->_tables[$tname . '.row'];
      }
      $change_actions = array('SET', 'SET+', 'DEL', 'DEL+');
      if ( in_array($select['action'], $change_actions, TRUE) ) {
        $records =& $this->_tables[$tname . '.row'];
      } else {
        $records = $this->_tables[$tname . '.row'];
      }
      $headers = array_keys($this->_tables[$tname . '.col']);
    } else {
      $this->_NeedTable($tname, TRUE);
      if ( is_null($select) ) {
        return $this->_cache[$tname . '.row'];
      }
      $records = $this->_cache[$tname . '.row'];
      $headers = array_keys($this->_cache[$tname . '.col']);
    }

    // basic info
    $where = $select['where'];
    $total = count($records);
    // set up range, like array_slice's (offset, length)
    $range = array(
        intval($select['range'][0]),
        intval($select['range'][1]) ?: $total
    );
    $range[0] = $range[0] < 0 ? $total + $range[0] : $range[0];
    if ( $range[0] < 0 ) {
        $range[0] = 0;
    }
    if ( $range[0] >= $total ) {
        $range[0] = $total;
    }
    $range[1] = $range[1] < 0 ? $total + $range[1] + 1 : $range[0] + $range[1];
    if ( $range[1] < 0 ) {
        $range[1] = 0;
    }
    if ( $range[1] > $total ) {
      $range[1] = $total;
    }

    switch ( $select['action'] ) {

      case 'GET':
        // get records within range
        $result = array();
        for ( list($i, $m) = $range; $i < $m; $i++ ) {
          if ( is_null($where) or $where($records[$i]) ) {
            $result[] = $records[$i];
          }
        }
        // sort records with specified order
        $this->_SortRecords($result, $select['order']);
        // only get data of specified column(s)
        $this->_SetColumn($result, $select['column'], $select['key']);
        return $result;

      case 'NUM':
        if ( is_callable($where) ) {
          $result = 0;
          for ( list($i, $m) = $range; $i < $m; $i++ ) {
            if ( $where($records[$i]) ) {
              $result++;
            }
          }
          return $result;
        }
        return $total;

      case 'MAX':
        $to_find_maximum = TRUE;
        // roll on
      case 'MIN':
        if ( is_array($select['column']) ) {
          $col_keys = $select['column'];
        } else {
          $to_single_value = TRUE;
          $col_keys = array($select['column']);
        }
        list($first_record) = array_slice($records, 0, 1);
        if ( empty($col_keys) ) {
          $col_keys = $headers;
        }
        $result = array_fill_keys($col_keys, NULL);
        foreach ( $col_keys as $key ) {
          $result[$key] = $first_record[$key];
        }
        if ( $to_find_maximum ) {
          if ( is_null($where) and $range[0] === 0 and $range[1] === $total ) {
            if ( !$fromFile ) {
              $maximums = $this->_tables[$tname . '.col'];
            } else {
              $maximums = $this->_cache[$tname . '.col'];
            }
            $result = array();
            foreach ( $col_keys as $key ) {
              $result[$key] = $maximums[$key];
            }
          } else {
            foreach ( $col_keys as $key ) {
              for ( list($i, $m) = $range; $i < $m; $i++ ) {
                if ( is_null($where) || $where($records[$i])
                  and $result[$key] < $records[$i][$key] )
                {
                  $result[$key] = $records[$i][$key];
                }
              }
            }
          }
        } else {
          foreach ( $col_keys as $key ) {
            for ( list($i, $m) = $range; $i < $m; $i++ ) {
              if ( is_null($where) || $where($records[$i])
                and $result[$key] > $records[$i][$key] )
              {
                $result[$key] = $records[$i][$key];
              }
            }
          }
        }
        return $to_single_value ? array_shift($result) : $result;

      case 'SET+':
        $to_return_records = TRUE;
        // roll on
      case 'SET':
        $records_cnt = 0;
        $selected_records = array();
        for ( list($i, $m) = $range; $i < $m; $i++ ) {
          if ( $where($records[$i]) ) {
            if ( $to_return_records ) {
              $selected_records[] = $records[$i];
            } else {
              $records_cnt++;
            }
          }
        }
        if ( !empty($selected_records) ) {
          $this->_SortRecords($selected_records, $select['order']);
          $this->_SetColumn($selected_records, $select['column'], $select['key']);
        }
        if ( $records_cnt > 0 or !empty($selected_records) ) {
          $tdata = array(
            'headers' => $headers,
            'records' => &$records
          );
          $this->_FormatHeaders($tdata, FALSE);
          $this->_tables[$tname . '.col'] = $tdata['headers'];
        }
        return $to_return_records ? $selected_records : $records_cnt;

      case 'DEL+':
        $to_return_records = TRUE;
        // roll on
      case 'DEL':
        $records_cnt = 0;
        $deleted_records = array();
        for ( list($i, $m) = $range; $i < $m; $i++ ) {
          if ( is_null($where) or $where($records[$i]) ) {
            if ( $to_return_records ) {
              $deleted_records[] = $records[$i];
            } else {
              $records_cnt++;
            }
            // indexes need re-mapping
            unset($records[$i]);
          }
        }
        // re-map indexes
        array_splice($records, 0, 0);
        if ( !empty($deleted_records) ) {
          $this->_SortRecords($deleted_records, $select['order']);
          $this->_SetColumn($deleted_records, $select['column'], $select['key']);
        }
        if ( empty($records) ) {
          $this->_ClearMaximum($tname);
        } else if ( $records_cnt > 0 or !empty($deleted_records) ) {
          $tdata = array(
            'headers' => $headers,
            'records' => &$records
          );
          $this->_FormatHeaders($tdata, FALSE);
          $this->_tables[$tname . '.col'] = $tdata['headers'];
        }
        return $to_return_records ? $deleted_records : $records_cnt;

      case 'UNI':
        if ( is_array($select['column']) ) {
          $col_keys = $select['column'];
        } else {
          $to_single_value = TRUE;
          $col_keys = array($select['column']);
        }
        if ( empty($col_keys) ) {
          $col_keys = $headers;
        }
        $result = array_fill_keys($col_keys, array());
        foreach ( $col_keys as $key ) {
          for ( list($i, $m) = $range; $i < $m; $i++ ) {
            if ( is_null($where) or $where($records[$i]) ) {
              $result[$key][] = $records[$i][$key];
            }
          }
        }
        foreach ( $col_keys as $key ) {
          $result[$key] = array_unique($result[$key], SORT_REGULAR);
        }
        $this->_SortUniqueValues($result, $select['order']);
        return $to_single_value ? array_shift($result) : $result;

      default:
        $this->_Error('SYNTAX_ERROR', 'Unknown error');
    }
  }
  /**
  * @info   Insert one record to a working table
  * @param  {String}  $tname: name of specified table
  * @param  {Array}   $record: one record to insert
  * @return void
  */
  public function Insert($tname, $record)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    $this->_NeedTable($tname, FALSE);
    $cached_headers = $this->_tables[$tname . '.col'];
    $header_names = array_keys($cached_headers);
    $tdata = array(
      'headers' => $header_names,
      'records' => array($record)
    );
    $this->_NeedValidTable($tdata);
    foreach ( $record as $name => $value ) {
      if ( $cached_headers[$name] < $value ) {
        $cached_headers[$name] = $value;
      }
    }
    $records = array($record);
    $this->_FormatRecordValues($header_names, $records);
    $this->_tables[$tname . '.col'] = $cached_headers;
    $this->_tables[$tname . '.row'][] = array_pop($records);
  }
  /**
  * @info   Merge records to a working table
  * @param  {String}  $tname: name of specified table
  * @param  {Array}   $records: records to merge with
  * @return void
  */
  public function Merge($tname, $records)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    $this->_NeedTable($tname, FALSE);
    $cached_headers = $this->_tables[$tname . '.col'];
    $header_names = array_keys($cached_headers);
    $this->_NeedValidTable(array(
      'headers' => $header_names,
      'records' => $records
    ));
    $this->_FormatRecordValues($header_names, $records);
    $index = $tname . '.row';
    foreach ( $records as $record ) {
      $this->_tables[$index][] = $record;
      foreach ( $cached_headers as $name => $maximum ) {
        if ( $maximum < $record[$name] ) {
          $cached_headers[$name] = $record[$name];
        }
      }
    }
    $this->_tables[$tname . '.col'] = $cached_headers;
  }
  /**
  * @info   Overwrite records of a working table
  * @param  {String}  $tname: name of specified table
  * @param  {Array}   $records
  * @return void
  */
  public function SetRecords($tname, $records)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    $this->_NeedFragmentLoaded($tname, TRUE);
    $cached_headers = $this->_tables[$tname . '.col'];
    $header_names = array_keys($cached_headers);
    $tdata = array(
      'headers' => $header_names,
      'records' => $records
    );
    $this->_NeedValidTable($tdata);
    $this->_FormatHeaders($tdata, FALSE);
    $this->_FormatRecordValues($header_names, $records);
    $this->_tables[$tname . '.col'] = $tdata['headers'];
    $this->_tables[$tname . '.row'] = $records;
  }
  /**
  * @info   Append record(s) directly to a table file
  * @param  {String}  $tname: name of specified table
  * @param  {Array}   $records: record(s) to append
  * @param  {Boolean} $isOneRecord: just one record?
  * @return void
  */
  public function Append($tname, $records, $isOneRecord = FALSE)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    if ( FALSE === $this->_ReadHeaders($tname) ) {
      $this->_Error('FILE_ERROR', 'Table not found or broken');
    }
    if ( $isOneRecord ) {
      $records = array($records);
    }
    $cached_headers = $this->_cache[$tname . '.col'];
    $header_names = array_keys($cached_headers);
    $tdata = array(
      'headers' => $header_names,
      'records' => $records
    );
    $this->_NeedValidTable($tdata);
    $this->_FormatHeaders($tdata, FALSE);
    foreach ( $tdata['headers'] as $name => $maximum ) {
      if ( $cached_headers[$name] < $maximum ) {
        $cached_headers[$name] = $maximum;
      }
    }
    $this->_FormatRecordValues($header_names, $records);
    $this->_WriteTable($tname, array(
      'headers' => $cached_headers,
      'records' => $records
    ), TRUE, FALSE);
  }
  /**
  * @info   Set headers of specified table
  * @param  {String}  $tname: name of specified table
  * @param  {Array}   $headers: headers to be kept
  * @return void
  */
  public function SetHeaders($tname, $headers)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    $this->_NeedValidTable(array(
      'headers' => $headers,
      'records' => array()
    ));
    $this->_NeedTable($tname, FALSE);
    $old_headers = array_keys($this->_tables[$tname . '.col']);
    $old_records = $this->_tables[$tname . '.row'];
    $this->_tables[$tname . '.row'] = array();
    $common_headers = array_intersect($old_headers, $headers);
    $cached_headers = array_fill_keys($headers, NULL);
    $new_records = array();
    $empty_record = array_fill_keys($headers, NULL);
    foreach ( $old_records as $index => $record ) {
      $new_record = $empty_record;
      foreach ( $common_headers as $header ) {
        $value = $record[$header];
        $new_record[$header] = $value;
        if ( $cached_headers[$header] < $value ) {
          $cached_headers[$header] = $value;
        }
      }
      $new_records[] = $new_record;
      unset($old_records[$index]);
    }
    $this->_tables[$tname . '.row'] = $new_records;
    $this->_tables[$tname . '.col'] = $cached_headers;;
  }
  /**
  * @info   Write a working table to the database.
  * @param  {String}  $tname: name of specified table
  * @return void
  */
  public function Update($tname)
  {
    $this->_NeedConnected();
    $this->_NeedValidName($tname);
    if ( !is_array($this->_tables[$tname . '.row']) ) {
      return;
    }
    if ( !is_array($this->_tables[$tname . '.col']) ) {
      $this->_NeedFragmentLoaded($tname, TRUE);
    }
    $this->_WriteTable($tname, array(
      'headers' => $this->_tables[$tname . '.col'],
      'records' => $this->_tables[$tname . '.row']
    ), FALSE, TRUE);
  }
  /**
  * @info   Empty the cache of records
  *         Empty all cache of records if $tname is NULL.
  * @param  {String}  $tname: (optional) name of specified table
  * @return void
  */
  public function EmptyCache($tname = NULL)
  {
    $this->_NeedConnected();
    if ( !is_null($tname) ) {
      $this->_NeedValidName($tname);
      unset($this->_cache[$tname . '.row']);
      return;
    }
    $this->_cache = array();
  }

  private function _Error($errType, $errMsg)
  {
    if ( !$this->_debug ) {
      exit();
    }
    $errMsg = @htmlentities($errMsg, ENT_COMPAT, 'UTF-8');
    echo <<<"EOT"
<pre style="white-space:pre-wrap;color:#B22222;">
<b>{$errType}:</b><br>
  <b>{$errMsg}</b>
</pre>
EOT;
    echo '<pre style="white-space:pre-wrap">Backtrace:<br>';
    @debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    echo '</pre>';
    exit();
  }
  private function _NeedConnected()
  {
    if ( !$this->_is_connected ) {
      $this->_Error('OPERATION_ERROR', 'Not connected to any database');
    }
  }
  private function _NeedValidName($tname)
  {
    if ( !is_string($tname) or !preg_match('/^\\w+$/', $tname) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid table name');
    }
  }
  private function _NeedTable($tname, $fromFile)
  {
    if ( FALSE === $this->_ReadTable($tname) ) {
      $this->_Error('FILE_ERROR', 'Table not found or broken');
    }
    if ( $fromFile ) {
      return;
    }
    if ( !is_array($this->_tables[$tname . '.col'])
      or !is_array($this->_tables[$tname . '.row']) )
    {
      $this->_tables[$tname . '.col'] = $this->_cache[$tname . '.col'];
      $this->_tables[$tname . '.row'] = $this->_cache[$tname . '.row'];
    }
  }
  private function _NeedFragmentLoaded($tname, $headers_or_records)
  {
    if ( $headers_or_records and !is_array($this->_tables[$tname . '.col']) ) {
      if ( FALSE === $this->_ReadHeaders($tname) ) {
        $this->_Error('FILE_ERROR', 'Table not found or broken');
      }
      $this->_tables[$tname . '.col'] = $this->_cache[$tname . '.col'];
    }
    if ( !$headers_or_records and !is_array($this->_tables[$tname . '.row']) ) {
      if ( FALSE === $this->_ReadTable($tname) ) {
        $this->_Error('FILE_ERROR', 'Table not found or broken');
      }
      $this->_tables[$tname . '.row'] = $this->_cache[$tname . '.row'];
    }
  }
  private function _FormatSelectInfo($tname, &$select, $fromFile)
  {
    if ( !(is_null($select) or is_array($select)) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid select');
    }
    $valid_actions = array(
      'GET', 'NUM', 'MAX', 'MIN', 'SET', 'DEL', 'SET+', 'DEL+', 'UNI'
    );
    $headers = $this->GetHeaders($tname, $fromFile);
    $select['action'] = strtoupper($select['action']) ?: 'GET';
    if ( !is_string($select['action'])
      or !in_array($select['action'], $valid_actions, TRUE) )
    {
      $this->_Error('SYNTAX_ERROR', 'Invalid select info "action"');
    }
    $select['range'] = $select['range'] ?: array();
    if ( !is_array($select['range'])
      or !(is_null($select['range'][0]) or is_integer($select['range'][0]))
      or !(is_null($select['range'][1]) or is_integer($select['range'][0])) )
    {
      $this->_Error('SYNTAX_ERROR', 'Invalid select info "offset"');
    }
    $select['where'] = $select['where'] ?: NULL;
    if ( !(is_null($select['where']) or is_callable($select['where'])) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid select info "where"');
    }
    $select['column'] = $select['column'] ?: array();
    if ( !(is_string($select['column']) or is_array($select['column'])) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid select info "column"');
    } else if ( is_array($select['column']) ) {
      if ( count(array_diff($select['column'], $headers)) > 0 ) {
        $this->_Error('SYNTAX_ERROR', 'Invalid select info "column"');
      }
    }
    if ( !(is_null($select['key'])
          or in_array($select['key'], $headers, TRUE)) )
    {
      $this->_Error('SYNTAX_ERROR', 'Invalid select info "key"');
    } else if ( is_string($select['key']) ) {
      if ( is_string($select['column']) && $select['key'] === $select['column']
        or in_array($select['key'], $select['column'], TRUE) )
      {
        $this->_Error('SYNTAX_ERROR', 'Invalid select info "key"');
      }
    }
    $select['order'] = $select['order'] ?: NULL;
    if ( !(is_null($select['order']) or is_array($select['order'])) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid select info "order"');
    } else if ( is_array($select['order']) ) {
      $valid_sort_flags = array(SORT_ASC, SORT_DESC);
      foreach ( $select['order'] as $key => $flag ) {
        if ( !in_array($key, $headers)
          or !in_array($flag, $valid_sort_flags, TRUE) )
        {
          $this->_Error('SYNTAX_ERROR', 'Invalid select info "order"');
        }
      }
    }
    if ( in_array($select['action'], array('SET', 'DEL', 'SET+', 'DEL+'), TRUE) )
    {
      if ( $fromFile ) {
        $this->_Error('SYNTAX_ERROR', 'Invalid select action');
      }
      if ( ($select['action'] === 'SET' or $select['action'] === 'SET+')
        and !is_callable($select['where']) )
      {
        $this->_Error('SYNTAX_ERROR', 'Invalid select action');
      }
    }
  }
  private function _SetColumn(&$records, $columnKeys, $indexKey)
  {
    if ( empty($records) ) {
      return;
    }
    if ( is_string($columnKeys) ) {
      $columnKeys = array($columnKeys);
    }
    $has_column_key = !empty($columnKeys);
    $has_index_key = !empty($indexKey);
    if ( !($has_column_key or $has_index_key ) ) {
      return;
    }
    list($first_record) = array_slice($records, 0, 1);
    $array_keys = array_keys($first_record);
    $column_keys = $columnKeys ?: array_diff($array_keys, array($indexKey));
    if ( $has_index_key ) {
      $key_column_values = array();
      $has_column_key = TRUE;
    }
    $order_keys = array_flip($column_keys);
    $other_keys = array_flip(array_diff($array_keys, $column_keys));
    $to_first_mode = count($other_keys) > (count($array_keys) / 2);
    foreach ( $records as $index => $record ) {
      if ( $has_index_key ) {
        $key_column_values[$index] = $record[$indexKey];
      }
      if ( $has_column_key ) {
        if ( $to_first_mode ) {
          $records[$index] = array_intersect_key($record, $order_keys);
        } else {
          $records[$index] = array_diff_key($record, $other_keys);
        }
        $new_record = array();
        foreach ( $column_keys as $key ) {
          $new_record[$key] = $records[$index][$key];
        }
        $records[$index] = $new_record;
      }
    }
    if ( $has_index_key ) {
      $new_records = array();
      foreach ( $records as $index => $record ) {
        $new_records[$key_column_values[$index]] = $record;
      }
      $records = $new_records;
    }
  }
  private function _SortUniqueValues(&$array, $sortFlags)
  {
    if ( is_null($sortFlags) or empty($array) ) {
      return;
    }
    foreach ( $sortFlags as $key => $flag ) {
      if ( $flag === SORT_ASC ) {
        sort($array[$key], SORT_REGULAR);
      } else {
        rsort($array[$key], SORT_REGULAR);
      }
    }
  }
  private function _SortRecords(&$records, $sortFlags)
  {
    if ( empty($records) or is_null($sortFlags) ) {
      return;
    }
    list($first_record) = array_slice($records, 0, 1);
    $array_keys = array_keys($first_record);
    $sort_flags = array_intersect_key($sortFlags, array_flip($array_keys));
    $sort_keys = array_keys($sort_flags);
    if ( empty($sort_flags) ) {
      return;
    }
    $columns = array();
    $sort_args = array();
    foreach ( $sort_keys as $key ) {
      foreach ( $records as $record ) {
        $columns[$key][] = $record[$key];
      }
      $sort_args[] =& $columns[$key];
      $sort_args[] = $sort_flags[$key];
    }
    $sort_args[] =& $records;
    call_user_func_array('array_multisort', $sort_args);
  }
  private function _ClearMaximum($tname) {
    $headers = array_keys($this->_tables[$tname . '.col']);
    $this->_tables[$tname . '.col'] = array_fill_keys($headers, NULL);
  }
  private function _GetSecureFileName($name)
  {
    if ( preg_match('/\\/\\?\.\\?\./', '/' . $name) ) {
      $this->_Error('OPERATION_ERROR', 'Insecure file name');
    }
    return $this->_db_path . '/' . $name;
  }
  private function _FormatRecordValues($headers, &$records)
  {
    foreach ( $headers as $header ) {
      foreach ( $records as $index => $record ) {
        $new_records[$index][$header] = $record[$header];
      }
    }
    $records = $new_records;
  }
  private function _FilterInput(&$val, $key)
  {
    if ( is_string($val) ) {
      $val = str_replace("\x00", '', $val);
    }
  }
  private function _FormatHeaders(&$tdata, $isNumericIndexed)
  {
    $headers = $tdata['headers'];
    $header_maximums = array();
    list($first_record) = array_slice($tdata['records'], 0, 1);
    if ( $isNumericIndexed ) {
      $indexes = array_flip($headers);
      foreach ( $headers as $header ) {
        $header_maximums[$header] = $first_record[$indexes[$header]];
        foreach ( $tdata['records'] as $record ) {
          $value = $record[$indexes[$header]];
          if ( $header_maximums[$header] < $value ) {
            $header_maximums[$header] = $value;
          }
        }
      }
    } else {
      foreach ( $headers as $header ) {
        $header_maximums[$header] = $first_record[$header];
        foreach ( $tdata['records'] as $record ) {
          $value = $record[$header];
          if ( $header_maximums[$header] < $value ) {
            $header_maximums[$header] = $value;
          }
        }
      }
    }
    $tdata['headers'] = array_combine($headers, $header_maximums);
  }
  private function _IsValidHeaders($headers)
  {
    if ( !is_array($headers) or empty($headers) ) {
      return FALSE;
    }
    foreach ( $headers as $header ) {
      // header must be non-empty string, non-numeric-string
      if ( !is_string($header)
        or !preg_match('/^\\w+$/', $header)
        or preg_match('/^\\d*$/', $header) )
      {
        return FALSE;
      }
    }
    return TRUE;
  }
  private function _IsValidRecords($records)
  {
    if ( !is_array($records) ) {
      return FALSE;
    }
    foreach ( $records as $record ) {
      if ( !is_array($record) ) {
        return FALSE;
      }
    }
    return TRUE;
  }
  private function _NeedValidTable($tdata)
  {
    if ( !is_array($tdata) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid table');
    }
    // 1-D Array: names of headers
    if ( !$this->_IsValidHeaders($tdata['headers']) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid headers');
    }
    // 2-D Array: header-value dicts of records
    if ( !$this->_IsValidRecords($tdata['records']) ) {
      $this->_Error('SYNTAX_ERROR', 'Invalid records');
    }
  }
  private function _ReadHeaders($tname, $waitIfLocked = TRUE)
  {
    // find cache
    if ( is_array($this->_cache[$tname . '.col']) ) {
      // 1-D Array: names of headers
      return TRUE;
    }
    $filename = $this->_GetSecureFileName($tname);
    if ( !is_readable($filename . '.col') ) {
      $this->_Error('FILE_ERROR', 'Table not found or broken');
    }
    $fh_col = @fopen($filename . '.col', 'rb');
    if ( $waitIfLocked ) {
      $is_locked = @flock($fh_col, LOCK_SH, $would_block);
    } else {
      $is_locked = @flock($fh_col, LOCK_SH | LOCK_NB, $would_block);
    }
    //if ( $would_block && !$waitIfLocked or !$is_locked ) {
    if ( !$is_locked ) {
      @fclose($fh_col);
      return FALSE;
    } else {
      $cts_col = @file_get_contents($filename . '.col', FALSE, NULL);
      @flock($fh_col, LOCK_UN);
      @fclose($fh_col);
      $headers = unserialize($cts_col);
      if ( FALSE === $headers or !is_array($headers) or empty($headers) ) {
        $this->_Error('FILE_ERROR', 'Broken data');
      }
      $this->_cache[$tname . '.col'] = $headers;
    }
    return TRUE;
  }
  private function _ReadRecords($tname, $waitIfLocked = TRUE)
  {
    // find cache
    if ( is_array($this->_cache[$tname . '.row']) ) {
      // 2-D Array: header-value dicts of records
      return TRUE;
    }
    $filename = $this->_GetSecureFileName($tname);
    if ( !is_readable($filename . '.row') ) {
      $this->_Error('FILE_ERROR', 'Table not found or broken');
    }
    $fh_row = @fopen($filename . '.row', 'rb');
    if ( $waitIfLocked ) {
      $is_locked = @flock($fh_row, LOCK_SH, $would_block);
    } else {
      $is_locked = @flock($fh_row, LOCK_SH | LOCK_NB, $would_block);
    }
    //if ( $would_block && !$waitIfLocked or !$is_locked ) {
    if ( !$is_locked ) {
      @fclose($fh_row);
      return FALSE;
    }
    $lines = @file($filename . '.row', FILE_IGNORE_NEW_LINES); // without EOL
    // remove blank lines cause by previous appending to empty file
    $lines_length = count($lines);
    while ( $lines_length > 0 and empty($lines[0]) ) {
      array_shift($lines);
      $lines_length--;
    }
    @flock($fh_row, LOCK_UN);
    @fclose($fh_row);
    $cts_rlines = array();
    $cts_rindex = -1;
    foreach ( $lines as $line ) {
      if ( "\x00" === $line[0] ) {
        $cts_rindex++;
        $cts_rlines[] = substr($line, 1);
      } else {
        $cts_rlines[$cts_rindex] .= "\n" . $line;
      }
    }
    $records = array();
    $headers = array_keys($this->_cache[$tname . '.col']);
    $headers_length = count($headers);
    foreach ( $cts_rlines as $cts_rline ) {
      $record = unserialize($cts_rline);
      if ( FALSE === $record
        or !is_array($record)
        or $headers_length !== count($record) )
      {
        $this->_Error('FILE_ERROR', 'Broken data');
      }
      $records[] = array_combine($headers, $record);
    }
    $this->_cache[$tname . '.row'] = $records;
    return TRUE;
  }
  private function _ReadTable($tname, $waitIfLocked = TRUE)
  {
    if ( !$this->_ReadHeaders($tname, $waitIfLocked)
      or !$this->_ReadRecords($tname, $waitIfLocked) ) {
      return FALSE;
    }
    return TRUE;
  }
  private function _WriteTable($tname, $tdata, $toAppend, $toOverwrite)
  {
    if ( $toAppend and $toOverwrite ) {
      $this->_Error('SYNTAX_ERROR', 'Unknown error');
    }
    $filename = $this->_GetSecureFileName($tname);
    if ( !($toOverwrite or $toAppend)
      and (is_file($filename . '.col') || is_file($filename . '.row')) )
    {
      $this->_Error('OPERATION_ERROR', 'Table already existed');
    }
    if ( $toOverwrite || $toAppend
      and (!is_writable($filename . '.col')
        or !is_writable($filename . '.row')) )
    {
      $this->_Error('FILE_ERROR', 'Data files not writable');
    }
    @ignore_user_abort(TRUE);
    // write headers
    if ( FALSE === @file_put_contents($filename . '.col', serialize($tdata['headers']), LOCK_EX) )
    {
      $this->_Error('FILE_ERROR', 'Fail to write data files');
    }
    $this->_cache[$tname . '.col'] = $tdata['headers'];
    // write records
    $write_mode = $toAppend ? (LOCK_EX | FILE_APPEND) : LOCK_EX;
    if ( FALSE === @flock($fh_row, LOCK_EX) ) {
      @fclose($fh_row);
      @ignore_user_abort(FALSE);
      $this->_Error('FILE_ERROR', 'Fail to write data files');
    }
    $lines = array();
    foreach ( $tdata['records'] as $record ) {
      array_walk($record, 'self::_FilterInput');
      $lines[] = "\x00" . serialize(array_values($record));
    }
    if ( isset($lines[0]) and $toAppend ) {
      $lines[0] = "\n" . $lines[0];
    }
    $lines = implode("\n", $lines);
    if ( FALSE === @file_put_contents($filename . '.row', $lines, $write_mode) )
    {
      @ignore_user_abort(FALSE);
      $this->_Error('FILE_ERROR', 'Fail to write data files');
    }
    @ignore_user_abort(FALSE);
    if ( $toOverwrite ) {
      $this->_cache[$tname . '.col'] = $tdata['headers'];
      $this->_cache[$tname . '.row'] = $tdata['records'];
    }
  }
  private function _UnlinkTable($tname)
  {
    $filename = $this->_GetSecureFileName($tname);
    if ( !(is_file($filename . '.col') and is_file($filename . '.row')) ) {
      $this->_Error('FILE_ERROR', 'Table not existed');
    }
    if ( !(@unlink($filename . '.col') and @unlink($filename . '.row')) ) {
      $this->_Error('FILE_ERROR', 'Fail to delete data files');
    }
    $this->EmptyCache($tname);
  }
}
?>
