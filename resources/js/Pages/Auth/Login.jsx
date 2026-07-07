import { useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e) {
        e.preventDefault();
        post('/login');
    }

    return (
        <div style={{ fontFamily: 'sans-serif', maxWidth: 360, margin: '4rem auto' }}>
            <h1>ATLAS Admin Login</h1>

            <form onSubmit={submit}>
                <div style={{ marginBottom: '1rem' }}>
                    <label htmlFor="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        style={{ display: 'block', width: '100%' }}
                        autoFocus
                    />
                    {errors.email && <div style={{ color: 'red' }}>{errors.email}</div>}
                </div>

                <div style={{ marginBottom: '1rem' }}>
                    <label htmlFor="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        style={{ display: 'block', width: '100%' }}
                    />
                    {errors.password && <div style={{ color: 'red' }}>{errors.password}</div>}
                </div>

                <label style={{ display: 'block', marginBottom: '1rem' }}>
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    {' '}Remember me
                </label>

                <button type="submit" disabled={processing}>Log in</button>
            </form>
        </div>
    );
}
