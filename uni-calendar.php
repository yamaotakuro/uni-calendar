<?php
/**
* @package UNIONNET_AddPlugin
* @version 1.0
*/
/*
Plugin Name: AddCalendar
Description: カレンダープラグイン
Author: Takuro Yamao
Version: 1.0
*/

add_action('init', 'AddCalendar::init');

class AddCalendar{

  static function init(){
    return new self();
  }
  public function __construct(){
    if (is_admin() && is_user_logged_in()) {
      add_action('admin_menu', [$this, 'uni_add_calendar_field']);
      add_action('admin_print_footer_scripts', [$this, 'admin_calendar_script']);
      add_action('wp_ajax_uni_cal_field_update_options', [$this, 'update_options']);
      
    }
    add_action('wp_print_styles', [$this, 'load_styles']);
    add_action('wp_print_scripts', [$this, 'load_scripts']);
    add_action('wp_footer', [$this, 'calendar_init']);
    add_action('init', [$this, 'get_post_data']);
    add_shortcode('uni_calendar',[$this, 'cal_short_code']);
  }
  
  // メニューを追加する
  public function uni_add_calendar_field(){
    add_menu_page(
      'カレンダー追加',
      'カレンダー追加',
      'read',
      'uni_add_calendar',
      [$this, 'uni_add_calendar'],
      plugins_url( 'images/smile.png', __FILE__ )
    );
  }
  

  //JS読み込み
  public function load_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('core' , plugin_dir_url(__FILE__) .'packages/core/main.min.js');
    wp_enqueue_script('list' , plugin_dir_url(__FILE__) .'packages/list/main.min.js');
    wp_enqueue_script('daygrid' , plugin_dir_url(__FILE__) .'packages/daygrid/main.min.js');
    wp_enqueue_script('gcal' , plugin_dir_url(__FILE__) .'packages/google-calendar/main.min.js');
    wp_enqueue_script('locales' , plugin_dir_url(__FILE__) .'packages/core/ja.js');
    wp_enqueue_script('momentjs' , plugin_dir_url(__FILE__) .'js/moment.js');
    wp_enqueue_script('ultradate' , plugin_dir_url(__FILE__) .'js/UltraDate.min.js');
    wp_enqueue_script('ultradate_ja' , plugin_dir_url(__FILE__) .'js/UltraDate.ja.min.js');
  }

  //CSS読み込み
  public function load_styles() {
    wp_enqueue_style('core' , plugin_dir_url(__FILE__) .'packages/core/main.min.css');
    wp_enqueue_style('list' , plugin_dir_url(__FILE__) .'packages/list/main.min.css');
    wp_enqueue_style('daygrid' , plugin_dir_url(__FILE__) .'packages/daygrid/main.min.css');
    wp_enqueue_style('uni_add_calendar' , plugin_dir_url(__FILE__) .'css/uni_add_calendar.css');
  }
  
  //FullCalendarの実行
  public function calendar_init(){
    //ポストデータをjsonで取得
    $json = $this->get_post_data();
    //設定データ取得
    $uni_cal_fields = $this->get_settings();
    $post_type = $uni_cal_fields[0];
  ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');
      var date = new UltraDate();
      var calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: [ 'dayGrid', 'list' ,'googleCalendar' ],
        header: {
          left: 'prev,next today',
          center: 'title',
          right: 'month,listYear'
        },
        firstDay : 1,
        locale: 'ja',
        timeZone: 'Asia/Tokyo',
        events: <?php echo $json; ?>,
        selectable: true,
        selectHelper: true,
        eventClick: function(info) {
          var str = moment(info.event.start).format( 'YYYY-MM-DD' );
          window.location.href = "/<?php echo $post_type; ?>?date=" + str +"";
        },
        dayRender: function(info) {
          date.setFullYear(
            info.date.getFullYear(),
            info.date.getMonth(),
            info.date.getDate()
          );
          var holiday = date.getHoliday();
          if (holiday !== "") {
            info.el.classList.add("fc-hol")
          }
        },
      });

      calendar.render();

    });

  </script>
  <?php
  }


  public function uni_add_calendar() {
    
    //管理画面初期表示

    $uni_cal_fields = $this->get_settings();
    ?>
    <h2>カレンダー用フィールド</h2>

      <form method="post" action="">
      
        <table id="uni_table">
          <tbody>
            <tr>
              <th>カスタム投稿名（スラッグ）</th>
              <td><input type="text" name="uni_cal_field[slug]" class="field_data uni_cal_field_slug" value="<?php echo $uni_cal_fields[0];?>"></td>
            </tr>
            <tr>
              <th>カスタムフィールド名</th>
              <td><input type="text" name="uni_cal_field[date]" class="field_data uni_cal_field_date" value="<?php echo $uni_cal_fields[1];?>"></td>
            </tr>
          </tbody> 
          
        </table>
        <div class="btn"><input type="button" class="button button-primary" value="保存" id="setting_field_update" name="update"></div>
        
      </form>
    <?php
  }
  
  public function cal_short_code(){
    
    return '<div id="calendar"></div>';
    
  }

  //管理画面のJSの実行
  public function admin_calendar_script(){
    ?>
    <script>
    (function($){
      $(function(){
        
        //設定ページでのAjax(保存する)
        function setting_update(type){
          var fieldObj ={};
          var arr = [];
          var slugVal = $('.uni_cal_field_slug').val();
          var dateVal = $('.uni_cal_field_date').val();

          arr.push(slugVal,dateVal);
          fieldObj = arr;   

          $.ajax({
            url : ajaxurl,
            type : 'POST',
            data : {action : 'uni_cal_field_update_options' ,uni_cal_field : fieldObj  },
          })
          .done(function(data) {
            if(type =="update"){
              alert('保存しました');
            }
          })
          .fail(function() {
            window.alert('失敗しました');
          });
        }

        //保存
        $('#setting_field_update').on('click',function(){
          setting_update('update');
        });
      
      });
    })(jQuery);

    

  </script>
  <?php
  }

  //Ajaxで受け取ったデータを保存
  public function update_options(){
    update_option('uni_cal_field',$_POST['uni_cal_field']);
    exit('保存しました。');
  }

  //保存したフィールドを取得
  public function get_settings(){
    $uni_cal_field= get_option('uni_cal_field');
    return $uni_cal_field;
  }

  public function get_post_data(){
    //設定データ取得
    $uni_cal_fields = $this->get_settings();
    $post_type = $uni_cal_fields[0];
    $loop_field = $uni_cal_fields[1];
    //直近4ヵ月のデータ取得
    global $post;
    $now = date_i18n('Y/m/d');
    $month = date_i18n('Y/m/d', strtotime('+4 month'));
    $week_name = array("日", "月", "火", "水", "木", "金", "土");
    $meta_query = array(
        array(
          'key' => 'date',
          'value'=>array( $now,  $month),
          'compare'=>'BETWEEN',
          'type'=>'DATE'
        )
      );
    $args = array(
      'post_type' => $post_type,
      'posts_per_page' => -1,
      'order' => 'ASC',
      'orderby' => 'meta_value',
      'meta_key' => 'date',
      'meta_query' => $meta_query
    );
    $query = new WP_Query($args);

    $date_arr = []; 
    if ($query->have_posts()) : 
      while ($query->have_posts()) : $query->the_post();
        $date = [];
        $title = [];
        $results= [];
        // $loops = get_post_meta( $post->ID, 'loop_agenda' , false );
        $loops = SCF::get($loop_field);

        foreach ((array)$loops as $loop) {
          $date[] = date_i18n( 'Y-m-d', strtotime( $loop['date'] ) );
          $w = date_i18n( 'w', strtotime( $loop['date'] ) );
          $title[] = get_the_title();
          $results[] = $week_name[$w];
        }
        $data = array (
          'date' => $date,
          'title' => $title
        );
        $date_arr[] = $data;
      endwhile;
    endif;
    wp_reset_postdata();
    
    //複数日程を分割してバラバラに配列化
    $split_date = [];
    $date_arr2 = [];
    foreach($date_arr as $split_post){
      $cnt = count($split_post['date']);
      if($cnt > 1){
        for($i=0; $i<$cnt; $i++){
          $data2 = array (
            'start' => $split_post['date'][$i],
            'end' => $split_post['date'][$i],
            'title' => '○'
          );
          $date_arr2[] = $data2;
        }
      }else{
        $data2 = array (
          'start' => $split_post['date'][0],
          'end' => $split_post['date'][0],
          'title' => '○'
        );
        $date_arr2[] = $data2;
      }
    }

    //日付の重複チェックしてあれば削除
    $tmp = [];
    $unique_data = [];
    foreach ($date_arr2 as $post){
        if (!in_array($post['start'], $tmp)) {
          $tmp[] = $post['start'];
          $unique_data[] = $post;
        }
    }
    //日付順にソート
    foreach ((array) $unique_data as $key => $value) {
      $sort[$key] = $value;
    }
    array_multisort($sort, SORT_ASC, $unique_data);
    $results = json_encode($unique_data);
    return $results;
  }
}