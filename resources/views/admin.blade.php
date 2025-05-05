<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background: #eef2f7;
            font-family: Arial, sans-serif;
        }
        .container {
            width: 90%;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 0px 10px 0px #aaa;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #3490dc;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #3490dc;
            color: white;
        }
        button {
            padding: 5px 10px;
            background: #38c172;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .logout {
            margin-top: 20px;
            text-align: center;
        }
        .logout button {
            background: #e3342f;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Admin Dashboard</h2>

    <h3>Users Management</h3>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Failed Attempts</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    <form method="POST" action="{{ url('/admin/role/'.$user->id) }}">
                        @csrf
                        <select name="role" onchange="this.form.submit()">
                            <option value="student" {{ $user->role === 'student' ? 'selected' : '' }}>Student</option>
                            <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="moderator" {{ $user->role === 'moderator' ? 'selected' : '' }}>Moderator</option>
                        </select>
                    </form>
                </td>
                                <td>{{ $user->failed_attempts }}</td>
                <td>
                    @if ($user->is_locked)
                        Locked
                    @else
                        Active
                    @endif
                </td>
                <td>
                    @if ($user->is_locked)
                    <form method="POST" action="{{ url('/admin/unlock/'.$user->id) }}">
                        @csrf
                        <button type="submit">Unlock</button>
                    </form>
                    @else
                        ---
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Login Attempt Logs</h3>
    <table>
        <thead>
            <tr>
                <th>Email</th>
                <th>IP Address</th>
                <th>Status</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
            <tr>
                <td>{{ $log->email }}</td>
                <td>{{ $log->ip_address }}</td>
                <td>
                    @if ($log->status)
                        <span style="color: green;">Success</span>
                    @else
                        <span style="color: red;">Failed</span>
                    @endif
                </td>
                <td>{{ $log->created_at }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <h3>Admin Action Logs</h3>
<table>
    <thead>
        <tr>
            <th>Admin</th>
            <th>Action</th>
            <th>IP Address</th>
            <th>Timestamp</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($adminLogs as $log)
        <tr>
            <td>{{ $log->admin_email }}</td>
            <td>{{ $log->action }}</td>
            <td>{{ $log->ip_address }}</td>
            <td>{{ $log->created_at }}</td>
        </tr>
        @endforeach
    </tbody>
</table>


<div class="logout">
    <button type="button" onclick="confirmLogout()">Logout</button>
</div>

<script>
    function confirmLogout() {
        Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Logout'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a new form element
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route('logout') }}';
                
                // Add CSRF token
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = document.querySelector('meta[name="csrf-token"]').content;
                form.appendChild(csrf);
                
                // Add to body and submit
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Update your session timeout function to use the same approach
    function resetTimer() {
        clearTimeout(logoutTimeout);
        logoutTimeout = setTimeout(() => {
            Swal.fire({
                icon: 'info',
                title: 'Session Expired',
                text: 'You have been logged out due to inactivity.',
                showConfirmButton: false,
                timer: 3000
            }).then(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route('logout') }}';
                
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = document.querySelector('meta[name="csrf-token"]').content;
                form.appendChild(csrf);
                
                document.body.appendChild(form);
                form.submit();
            });
        }, logoutAfter);
    }
</script>

</body>
</html>
