<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Setting;
use Auth;
use Session;
use Redirect;
use Validator;
use Crypt;
use DateTime;

/**
* UserController is a sample class for demonstrating a Laravel controller class
*
* This class has no real actual code, but merely exits
* to demonstrate a normal Laravel controller class
*
* @package  Users
* @author   Sagar Gurnani <sagar@lucidsoftech.com>
* @version  1.0.0
* @access   public
*/
class UserController extends Controller
{
    /**
     * index()
     * 
     * Display a user dashboard or redirect to home.
     *
     * @return Mixed
     */
    public function index()
    {
        $user = new User;
        $userDetails = $user->getActiveUserDetailById(Session::get('userData')->id);

        return view('frontend.user.dashboard')->with('userDetails', $userDetails);
    }

    /**
     * create()
     * 
     * Show view for creating new User.
     *
     * @return  View 
     */
    public function create()
    {
        return view('frontend.user.user_register');
    }

    /**
     * store()
     * 
     * Store newly created User.
     *
     * @param  \Illuminate\Http\Request  $request
     * 
     * @return Route
     */
    public function store(Request $request)
    {
        // Validating user input
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'mobileNo'     => 'required|max:10|min:10',
            'residentType' => 'required|string',
            'password'     => 'required|string|min:6|confirmed',
        ]);
        Session::put('newUserDetails', $request->all());

        return redirect('/addAppartment');
    }

    /**
     * changeUserPassword()
     * 
     * Show view for change user password.
     * 
     * @return Mixed
     */
    public function changeUserPassword()
    {
        $user = new User;
        $userDetails = $user->getActiveUserDetailById(Session::get('userData')->id);

        return view('frontend.user.change_password')->with('userDetails', $userDetails);
    }

    /**
     * forgotPassword()
     * 
     * Return view for forgot password link.
     *
     * @return View
     */
    public function forgotPassword()
    {
        return view('frontend.user.reset_email');
    }

    /**
     * create_user
     * 
     * Create user .
     *
     * @param  $appartment_id Integer
     * 
     * @param  $userType String
     * 
     * @return Mixed
     */
    public function create_user($appartment_id, $userType) 
    {    
        // Check if New User Details exist in session storage
        if(isset($appartment_id) && Session::get('newUserDetails')) {
            // Get New User details from Session Storage
            $newUserDetails = Session::get('newUserDetails');

            // Validate user data
            $validator = Validator::make($newUserDetails, [
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'mobileNo' => 'required|max:10|min:10',
            ]);
            // If Validators fails return back with errors
            if ($validator->fails()) {
                Session::flash('error_message', 'User information is missing');

                return redirect('/registerUser');
            }

            //Creating new user.
            $userData = [
                'name'           => $newUserDetails['name'],
                'email'          => $newUserDetails['email'],
                'contact_number' => $newUserDetails['mobileNo'],
                'appartment_id'  => $appartment_id,
                'password'       => bcrypt( $newUserDetails['password']),
                'user_type'      => $userType,
                'resident_type'  => $newUserDetails['residentType']
            ];

            $user        = new User();
            $userDetails = $user->createUser($userData);
            // Flush new user details from session
            Session::flush('newUserDetails');
            
            // Send email verification mail
            if (!empty($userDetails)) {
                $mailView    =  'frontend.emails.email_verification';
                $data        = ['user' => $userDetails];
                $mailFrom    = [
                                    'mail' => 'testlucid742@gmail.com',
                                    'name' => 'Hooked Homes'
                                ];
                $mailTo      = [
                                    'mail' => $userDetails->email,
                                    'name' => $userDetails->name 
                                ];
                $subject     = 'Email verification link';

                // Mail function defined in Controller.php 
                $status = (new Controller())->mail($mailView , $data, $mailFrom, $mailTo, $subject);

                if($status) {
                    Session::flash('success_message', 'User registered successfully');

                    return redirect('/registerUser');
                } else {
                    Session::flash('error_message', 'Sorry, unable to register');

                    return redirect('/registerUser');
                }
            } else {
                Session::flash('error_message', 'Sorry, unable to register');

                return redirect('/registerUser');
            }
        } else {
            // Return to sign in route.
            Session::flash('error_message', 'Some error occured while saving data');

            return redirect('/registerUser');
        }
    }

    /**
     * addFlatDetails()
     * 
     * Add user flat details.
     *
     * @param  \Illuminate\Http\Request  $request
     * 
     * @return Mixed
     */
    public function addFlatDetails(Request $request)
    {
        $request->validate([
            'block'        => 'required|string',
            'unitNo'       => 'required|string',
            'residentType' => 'required|string'
        ]);

        $user = new User();
        $userDetails = $user->getActiveUserDetailById(Auth::user()->id);

        if(!empty($userDetails)) {
            $newUserDetails = $user->updateFlatDetails(
                $userDetails->id, 
                $request->input('block'),
                $request->input('unitNo'), 
                $request->input('residentType') 
            );

            Session::put('userData', $newUserDetails);
            Session::flash('success_message', 'Residence details saved successfully');

            return Redirect::back();
        } else {
            Session::flash('error_message', 'Unable to Add Residence Details');

            return Redirect::back();
        }
    }

    /**
     * sendAdminMail()
     * 
     * Send admin mail for creating sser as sdmin.
     * 
     * @return Void
     */
    public function sendAdminMail()
    {
        $user = new User();
        $userDetails = $user->getActiveUserDetailById(Auth::user()->id);

        if(!empty($userDetails)) {
            // Save send mail admin status
            $newUserDetails = $user->updateUserAdminStatus($userDetails->id, 'pending');
            // Get admin email id's
            $allAdminDetails = $user->getAppartmentAllUsers($userDetails->appartment_id, 'admin'); 

            // Send mail
            if(!empty($allAdminDetails->toArray())) {
                foreach ($allAdminDetails as $adminDetails) {
                    $mailView =  'frontend.emails.work_as_admin';
                    $data     = ['user' => $userDetails];
                    $mailFrom = [
                        'mail' => $userDetails->email,
                        'name' => $userDetails->name
                    ];
                    $mailTo   = [
                        'mail' => $adminDetails->email,
                        'name' => $adminDetails->name 
                    ];
                    $subject  = 'Make User As Admin';

                    // Mail function defined in Controller.php 
                    $status = $this->mail($mailView , $data, $mailFrom, $mailTo, $subject);

                    if($status) {
                        Session::flash('success_message', 'Mail Send Successfully');

                        return Redirect::back();
                    } else {
                        Session::flash('error_message', 'Unable to send Admin Request');

                        return Redirect::back();
                    }
                }
            }    
        }
    } else {
        Session::flash('error_message', 'Unable to send Admin Request');

        return Redirect::back();
    }
}

   /**
     * removeUser()
     * 
     * Remove User.
     * 
     * @param $id Integer
     *
     * @return Void
     */
   public function removeUser($id)
   {
        if(isset($id)) {
            $user = new User();
            $data = $user->deleteUser($id);
            Session::flash('success_message', 'User Account Deleted Successfully');

            return Redirect::back();
        } else {
            Session::flash('error_message', 'Some error occured');

            return Redirect::back();
        }
   }

    /**
     * makeUserAsAdmin()
     * 
     * Make user as admin.
     * 
     * @param $id Integer
     *
     * @return Void
     */
    public function makeUserAsAdmin($id)
    {
        if(isset($id)) {
            $adminData    = Auth::user();
            $appartmentId = $adminData->appartment_id;
            $user         = new User();
            $adminCount   = $user->getAppartmentAllUsers($appartmentId, 'admin');

            // Get maximum admin count per appartment
            $setting = new Setting();
            $maxAdminCount = $setting->getMaxAdminCount();

            if(count($adminCount) < $maxAdminCount) {
                $userDetails = $user->getUserDetails($id, 'user', 'active');
                
                if(!empty($userDetails)) { 
                    $userDetails = $user->updateAdminDetails($userDetails->id);
                    $mailView    =  'admin.emails.approved_as_admin';
                    $data        = ['user' => $userDetails];
                    $mailFrom    = [
                        'mail' => $adminData->email,
                        'name' => $adminData->name
                    ];
                    $mailTo      = [
                        'mail' => $userDetails->email,
                        'name' => $userDetails->name 
                    ];
                    $subject     = 'Your Approved As an Admin';

                    // Mail function defined in Controller.php 
                    $status = $this->mail($mailView , $data, $mailFrom, $mailTo, $subject);

                    if($status) {
                        Session::flash('success_message', 'Mail Send Successfully');

                        return Redirect::back();
                    } else {
                        Session::flash('error_message', 'Unable to send Admin Request');

                        return Redirect::back();
                    }

                    Session::flash('success_message', 'User Successfully Updated');

                    return Redirect::back();
                } else { 
                    Session::flash('error_message', 'Unable to find User Details');

                    return Redirect::back();
                }
                Session::flash('success_message', 'User Account Deleted Successfully');

                return Redirect::back();
            } else {
                Session::flash('error_message', 'Unable to Make this User as Admin, Admin Limit per Appartment exceeded');

                return Redirect::back();
            }

        } else {
            Session::flash('error_message', 'Some error occured');

            return Redirect::back();
        }
    }

    /**
     * applyUserFilters()
     * 
     * Apply user's filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * 
     * @return Collection
     */
    public function applyUserFilters(Request $request)
    {
        if($request['userType'] || $request['residentType'] || $request['approveStatus'] || $request['paymentStatus'] || $request['userStatus'] || $request['accountStatus']) {
            $user = new User();
            $filteredData = $user->getUserDataUsingFilters($request['userType'], $request['residentType'], $request['approveStatus'], $request['paymentStatus'], $request['userStatus'], $request['accountStatus']);

            return $filteredData;

        } else {
            Session::flash('error_message', 'Select atleast one filter for result');

            return Redirect::back();
        }     
    }
}
