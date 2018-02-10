<?php

namespace App\Http\Controllers;

use App\Role;
use App\Models\Billing\Transactions;
use App\User;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:create-users', ['only' => ['registerUser','newRole']]);
        $this->middleware('permission:read-users', ['only' => ['users','user','findUser','export','birthdays']]);
        $this->middleware('permission:update-users', ['only' => ['updateUser','updateUserRoles']]);

        $this->middleware('permission:read-profile', ['only' => ['profile']]);
        $this->middleware('permission:update-profile', ['only' => ['updateProfile']]);

    }

    /**
     * list all users
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    function users()
    {
        $users = User::get();
        return view('admin.users', compact('users'));
    }


    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    function user($id)
    {
        $user = User::whereId($id)->first();
        $roles = Role::all();
        $gifts = Transactions::whereUserId($user->id)->simplePaginate(50);
        return view('admin.user', compact('user', 'gifts', 'roles'));
    }

    /**
     * register new user
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    function registerUser(Request $request)
    {
        $validator = Validator::make($request->all(),
            [
                'username' => 'required|max:50|unique:users',
                'email' => 'required|email|max:255|unique:users',
                'first_name' => 'required',
                'last_name' => 'required',
                'phone' => 'required'
            ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $confirmation_code = str_random(30);

        //create stripe customer
        $customer = Transactions::createCustomer($request);

        $password = str_random(6);

        //create customer
        $user = new User();
        $user->username = $request->username;
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->password = bcrypt($password);
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->address = $request->address;
        $user->company = $request->company;
        $user->created_at = date('Y-m-d H:i:s');
        $user->confirmation_code = $confirmation_code;
        $user->stripe_id = $customer->id;
        $user->status=1;
        $user->save();

        //give basic role
        $user->attachRole($request->role);

        //notify user to activate account
        if($request->has('notify-user')){
        Mail::send('emails.accounts-verify', [
            'email' => $request->email,
            'password' => $password,
            'confirmation_code' => $confirmation_code],
            function ($m) use ($request) {
                $m->from(config('mail.from.address'),config('app.name'));
                $m->to($request['email'], $request['first_name'])->subject(__("Your new account"));
            });

        flash()->success(__("Thanks for signing up! Confirmation email has been sent"));
        }

        return redirect()->back();
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    function updateUser(Request $request, $id)
    {
        $rules = [
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email|unique:users,email,' . $id,

        ];

        if ($request->has('password') && trim($request->password) !=="") {
            $rules2 = [
                'password' => 'min:6|confirmed',
                'password_confirmation' => 'min:6'
            ];
            $rules = array_collapse([$rules, $rules2]);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $user = User::find($id);
        if (Input::has('password')) {
            $user->password = bcrypt($request['password']);
        }

        //generate username in case it wasn't during installation
        if(empty($user->username))
            $user->username=strtolower($request->last_name.rand(1,100));

        $user->email = $request->email;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->phone = $request->phone;
        $user->address = $request->address;
        $user->company = $request->company;
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        flash()->success(__("Profile updated"));
        return redirect()->back();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    function updateUserRoles(Request $request)
    {
        $user = User::find($request->id);

        //admin cant take away their rights
        $me = User::find(Auth::user()->id);

        if ($me->hasRole('admin') && Auth::user()->id == $request->id) {
            flash()->error(__("You cannot change your own rights. Another admin should"));
        } else {
            //remove all
            DB::table('role_user')->where('user_id',$request->id)->delete();
            $user->attachRole($request->role);
            flash()->success(__("Roles updated"));
        }

        return redirect()->back();
    }


    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    function userAccount()
    {
        $txns = Transactions::whereUserId(Auth::user()->id)->simplePaginate('50');
        return view('account.dashboard', compact('txns'));
    }

    /**
     * get current  user profile
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    function profile()
    {
        $id = Auth::user()->id;
        $user = User::find($id);
        return view('account.profile', compact('user'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    function updateProfile(Request $request)
    {
        $id = Auth::user()->id;

        $rules = [
            'username'=>'required|unique:users,username,' . $id,
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email|unique:users,email,' . $id,
            'dob'=>'date_format:Y-m-d'
        ];
        if (Input::has('password')) {
            $rules2 = [
                'password' => 'min:6|confirmed',
                'password_confirmation' => 'required|min:6'
            ];
            $rules = array_collapse([$rules, $rules2]);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::find($id);

        if ($user->stripe_id == null || $user->stripe_id == "") {//create stripe customer
            $customer = Transactions::createCustomer($request);
            $user->stripe_id = $customer->id;
        }

        if (Input::has('password')) {
            $user->password = bcrypt($request['password']);
        }

        $user->username = $request['username'];
        $user->email = $request['email'];
        $user->first_name = $request['first_name'];
        $user->last_name = $request['last_name'];
        $user->phone = $request['phone'];
        if($request->has('dob'))
            $user->dob = $request->dob;
        $user->address=$request->address;
        $user->about=$request->about;
        $user->updated_at = date('Y-m-d H:i:s');
        $user->save();

        flash()->success(__("Profile updated"));
        return redirect()->back();
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    function newRole(Request $request)
    {
        $rules = [
            'name' => 'required|max:50|unique:roles',
            'slug' => 'required|max:50|unique:roles',
            'description' => 'required',

        ];

        $validator = Validator::make($request->all(),
            $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $role = new Role();
        $role->name = $request['name'];
        $role->slug = $request['slug'];
        $role->description = $request['description'];
        $role->save();

        flash()->success(__("Role added"));
        return redirect()->back();
    }

    /**
     * find users
     */
    function findUser()
    {
        $users = User::get();
        echo json_encode($users);
    }

    /**
     * todo
     * @param null $month
     * @param null $year
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    function birthdays()
    {

        if (isset($_GET['y']))
            $year = $_GET['y'];
        else
            $year = "%";

        if (isset($_GET['m']))
            $month = sprintf("%02d", $_GET['m']);
        else
            $month = date('m');

        if (isset($_GET['d']))
            $day = $_GET['d'];
        else
            $day = "%";

        $users = User::where('dob', 'LIKE', "$year-$month-$day")->get();

        $months = array();
        for ($i = 1; $i <= 12; $i++) {
            $timestamp = mktime(0, 0, 0, date('n') - $i, 1);
            $months[date('n', $timestamp)] = date('F', $timestamp);
        }
        ksort($months);
        return view('admin.birthdays', compact('users', 'months'));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    function export(Request $request){
        $fileName= 'users_export.csv';

        $users= User::select($request->col)->get();
        $fp = fopen($fileName, 'w');
        fputcsv($fp, $request->col);

        foreach($users as $key=>$item){
            fputcsv($fp, $item->toArray());
        }
        fclose($fp);
        return Response::download($fileName)->deleteFileAfterSend(true);

    }
}
