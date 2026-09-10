/**
 * Scaffolding: proves the Blade -> React -> axios -> API chain works.
 * Delete this island along with the /api/ping endpoint and the home view.
 */
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';

type Pong = {
    message: string;
    at: string;
};

export default function Ping() {
    const [pong, setPong] = useState<Pong | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const check = async () => {
        setLoading(true);
        setError(null);

        try {
            const { data } = await api.get<Pong>('/ping');
            setPong(data);
        } catch {
            setError('The API did not answer.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void check();
    }, []);

    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border p-6">
            <p className="text-sm">
                {loading && 'Calling /api/ping...'}
                {error && <span className="text-destructive">{error}</span>}
                {! loading && ! error && pong && (
                    <>
                        API says <strong>{pong.message}</strong> at {pong.at}
                    </>
                )}
            </p>

            <Button onClick={check} disabled={loading}>
                Call the API again
            </Button>
        </div>
    );
}
