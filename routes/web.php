<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {

    //bài tập PHẦN 1: Chuyển đổi câu SQL về Query Builder or Eloquent 
    //1 .Truy vấn kết hợp nhiều bảng (JOIN)
    $users = DB::table('users', 'u')
        ->select('u.name', 'SUM(o.amount) as total_spent')
        ->join('order as o', 'u.id', '=', 'o.user_id')
        ->groupBy('u.name')
        ->having('total_spent', '>', 1000)
        ->toRawSql();
    dd($users);

    // 2. Truy vấn thống kê dựa trên khoảng thời gian (Time-based statistics)
    $orders = DB::table('orders')
        ->select('DATE(order_date) as date', 'COUNT(*) as orders_count', 'SUM(total_amount) as total_sales')
        ->whereBetween('order_date', ['2024-01-01', '2024-09-30'])
        ->groupBy('DATE(order_date)')
        ->toRawSql();
    dd($orders);
    // 3. Truy vấn để tìm kiếm giá trị không có trong tập kết quả khác (NOT EXISTS)
    $products = DB::table('products', 'p')
        ->select('product_name')
        ->whereNotExists(function (Builder $query) {
            $query->select('1')
                ->from('orders', 'o')
                ->where('o.product_id', '=', 'p.id');
        })->toRawSql();
    dd($products);
    // 4. Truy vấn với CTE (Common Table Expression) em không làm được ạ
    // 5. Truy vấn lấy danh sách người dùng đã mua sản phẩm trong 30 ngày qua, cùng với thông tin sản phẩm và ngày mua
    $users = DB::table('users')
        ->select('users.name', 'products.product_name', 'orders.order_date')
        ->join('orders', 'users.id', '=', 'orders.user_id')
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('products', 'order_items.product_id', '=', 'products.id')
        ->where('orders.order_day', '>=',  DB::raw('NOW() - INTERVAL 30 DAY'))
        ->toRawSql();
    dd($users);
    //6. Truy vấn lấy tổng doanh thu theo từng tháng, chỉ tính những đơn hàng đã hoàn thành  
    $orders = DB::table('orders')
        ->select(DB::raw("DATE_FORMAT(orders.order_date, '%Y-%m') as order_month"), DB::raw('order_items.quantity * order_items.price as total_revenue'))
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->where('orders.status', 'completed')
        ->groupBy('order_month')
        ->orderByDesc('order_moth')
        ->toRawSql();
    dd($orders);
    // 7. Truy vấn các sản phẩm chưa từng được bán (sản phẩm không có trong bảng order_items)
    $products = DB::table('products')
        ->select('products.product_name')
        ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
        ->whereNull('order_items.product_id')
        ->toRawSql();
    dd($products);
    // 8.Lấy danh sách các sản phẩm có doanh thu cao nhất cho mỗi loại sản phẩm
    $products = DB::table('products', 'p')
        ->select('p.category_id', 'p.product_name', DB::raw("MAX(oi.total) as max_revenue"))
        ->join(DB::raw('(
            SELECT product_id, SUM(quantity * price) as total
            FROM order_items
            GROUP BY product_id
        ) as oi'), 'p.id', '=', 'oi.product_id')
        ->groupBy('p.category_id', 'p.product_name')
        ->orderByDesc('max_revenue')
        ->toRawSql();
    dd($products);
    // 9.Truy vấn thông tin chi tiết về các đơn hàng có giá trị lớn hơn mức trung bình
    $query = DB::table('orders')
        ->join('users', 'users.id', '=', 'orders.user_id')
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->select('orders.id', 'users.name', 'orders.order_date', DB::raw('SUM(order_items.quantity * order_items.price) AS total_value'))
        ->groupBy('orders.id', 'users.name', 'orders.order_date')
        ->having('total_value', '>', function ($query) {
            $query->select(DB::raw('AVG(total)'))
                ->from(DB::raw('(SELECT SUM(quantity * price) AS total FROM order_items GROUP BY order_id) AS avg_order_value'));
        });

    // In ra câu truy vấn SQL
    dd($query->toSql());
    // 10.Truy vấn tìm tất cả các sản phẩm có doanh số cao nhất trong từng danh mục (category)
    $products = DB::table('products as p')
        ->join('order_items as oi', 'p.id', '=', 'oi.product_id')
        ->select('p.category_id', 'p.product_name', DB::raw('SUM(oi.quantity) AS total_sold'))
        ->groupBy('p.category_id', 'p.product_name')
        ->having('total_sold', '=', function ($subQuery) {
            $subQuery->select(DB::raw('MAX(sub.total_sold)'))
                ->from(DB::raw('(SELECT product_name, SUM(quantity) AS total_sold 
                             FROM order_items 
                             JOIN products ON order_items.product_id = products.id 
                             GROUP BY product_name) as sub'));
        });

    dd($products->toSql());
    // PHẦN 2: TÌM HIỂU VỀ ELOQUENT

    //1) Eloquent ORM là gì trong Laravel? 
    // là một hệ thống tích hợp trong laravel. Eloquent giúp chuyển đổi các bản ghi trong cơ sở dữ liệu thành các đối tượng PHP

    //2) Laravel Eloquent có các quy ước mặc định nào khi ánh xạ giữa tên model và bảng trong cơ sở dữ liệu?
    //  1. Tên bảng mặc định (ví dụ : Model User sẽ tương ứng với bảng users.)
    //  2. Khóa chính mặc định : Eloquent mặc định sử dụng cột id làm khóa chính cho bảng.
    //  3. Tăng tự động (Auto-incrementing): Khóa chính mặc định sẽ tự động tăng
    //  4. Loại khóa chính (Primary Key Type): Khóa chính mặc định là số nguyên (integer).
    //  5. Dấu thời gian (Timestamps): 
        // Quy ước: Mặc định, Eloquent mong đợi bảng của bạn có hai cột created_at và updated_at để lưu dấu thời gian khi bản ghi được tạo hoặc cập nhật.
        // created_at: Tự động lưu thời gian tạo bản ghi.
        // updated_at: Tự động lưu thời gian cập nhật bản ghi gần nhất.
    // 6. Tên bảng pivot cho quan hệ Many-to-Many: Trong quan hệ nhiều-nhiều (many-to-many), Eloquent mặc định sử dụng tên của hai bảng liên quan, được sắp xếp theo thứ tự bảng chữ cái và nối với nhau bằng dấu gạch dưới (_) để đặt tên cho bảng pivot. Ví dụ : Nếu bạn có hai model User và Role, bảng pivot mặc định sẽ là role_user.
    // 7. Khóa ngoại (Foreign Key): Laravel sẽ tự động suy đoán khóa ngoại trong các mối quan hệ dựa trên tên của model và nối với hậu tố _id. Ví dụ: Quan hệ User với Post thông qua khóa ngoại mặc định là user_id trong bảng posts.
    // 8. Khóa ngoại của bảng pivot: Trong các mối quan hệ nhiều-nhiều, khóa ngoại cho mỗi bảng trong bảng pivot sẽ được suy ra dựa trên tên model liên quan và hậu tố _id. ví dụ: Trong bảng pivot role_user, Laravel sẽ mong đợi các cột user_id và role_id.

    //3) Làm thế nào để thay đổi tên bảng (table) và khóa chính (primary key) mặc định trong Eloquent?
        // 1. Thay đổi tên bảng (table) mặc định
        //Eloquent sử dụng quy ước lấy tên model và chuyển nó thành dạng số nhiều để làm tên bảng trong cơ sở dữ liệu. Nếu bạn muốn chỉ định một tên bảng khác không theo quy ước này, bạn có thể sử dụng thuộc tính $table.
        // ví dụ: Giả sử bạn có một model Product và bạn muốn ánh xạ model này tới bảng có tên là product_items thay vì bảng mặc định products.

        //2.khóa chính (primary key) mặc định
        //Eloquent mong đợi cột khóa chính có tên là id. Nếu bảng của bạn sử dụng một cột khác làm khóa chính, bạn có thể thay đổi điều này bằng cách sử dụng thuộc tính $primaryKey.
        
    //4) CRUD với Eloquent ORM như nào?
    //Trong Laravel, Eloquent ORM cung cấp các phương thức mạnh mẽ để thực hiện các thao tác CRUD (Create, Read, Update, Delete) với cơ sở dữ liệu thông qua các mô hình (model).










    //insertOrIgnore : nếu dữ liệu truyền vào bị trùng thì bỏ qua
    // DB::table('users')->insert([
    //     'email' => 'kayla@example.com', // key là tên cột => value : giá trị chuyền vào
    //     'votes' => 0
    // ]);

    // echo DB::table('users')
    //     ->inRandomOrder()->limit(10)->toRawSql();
    // die();

    //giảm dần
    //    echo DB::table('users')
    //                 ->orderByDesc('name')
    //                 ->toRawSql();
    //     die();

    // $users = DB::table('users')->where(function (Builder $query) {
    //     $query->select('type')
    //         ->from('membership')
    //         ->whereColumn('membership.user_id', 'users.id')
    //         ->orderByDesc('membership.start_date')
    //         ->limit(1);
    // }, 'Pro')->toRawSql();
    // dd($users);

    // echo DB::table('users')
    //     ->whereDate('created_at', '2016-12-31')
    //     ->toRawSql();
    // die();
    // where not : lấy ra bản ghi ngoại trừ ...
    // echo DB::table('product')
    //     ->whereNot(function (Builder $query) {
    //         $query->where('clearance', true)
    //             ->orWhere('price', '<', 10);
    //     })
    //     ->toRawSql();
    // die();

    // where or
    // $users = DB::table('users')->where(function (Builder $query) {
    //     $query->where('name', 'Abigail')
    //           ->orwhere('votes', '>', 50);
    // })
    // ->orwhere('is_vip', true) // orwhere là hoặc 
    // ->toRawSql();
    // dd($users);


    //    echo DB::table('users') join nâng cao
    //         ->join('contacts', function (JoinClause $join) {
    //             $join->on('users.id', '=', 'contacts.user_id')
    //             ->where('contacts.user_id', '>',100); // where của join khác where bên ngoài 
    //         })
    //         ->where('status','=',1)
    //         ->toRawSql();
    //         die();

    // join vào các bảng
    // $users = DB::table('users', 'u')
    //     ->join('contacts as c', 'u.id', '=', 'c.user_id')
    //     ->join('orders as o ', 'u.id', '=', 'o.user_id')
    //     ->select('u.*', 'c.phone as c_phone', 'o.price as o_price')
    //     ->toRawSql();
    // dd($users);

    // $users = DB::table('users')
    //     ->selectRaw('count(*) as user_count, email')
    //     ->where('email', '<>', 1)
    //     ->groupBy('email')
    //     ->get();
    // dd($users);

    // $query = DB::table('users')->select('name', 'email as user_email');

    // $users = $query
    //     ->limit(10)
    //     ->get();
    // $user2 = $query
    //     ->addSelect('password') // lấy thêm dữa liệu 
    //     ->limit(10)
    //     ->get();
    // dd($users, $user2);

    // avg, sum, max, min 
    // $count = $query->count();
    // $avg = $query->where('id','>',100)->avg('id');
    // $sum = $query->sum('id');
    // $min = $query->min('id');
    // $max = $query->max('id');
    // dd($count, $sum, $min, $max, $avg);
    //    $query->orderBy('id')->lazy()->each(function (object $user) {
    //         // ...
    //     });

    // dd($query->toRawSql());

    // $first = $query->findOr(15, function () {
    //     abort(404);
    // });
    // dd($first);
    // $users = DB::table('users')->get();

    // dd($users->toArray()); dùng để debug
    // first lấy 1 hàng/ cột duy nhất
    // pluck nhận danh sách giá trị một cột nào đó
    // $name = $query->pluck('name','email')->all();
    // chuck lấy ra dữ liệu phân loại ví dụ 1000 thì lấy 100
    // distinct trả về kết quả không bị trùng
    // latest mới nhất cho lên đầu
    //oldest cũ nhất lên đầu
    //inRandomOrder : sắp xếp ran dum

    // foreach ($users as $user) {
    //     dd( $user->name );
    //     die;
    // }
    //.
    return view('welcome');
});
