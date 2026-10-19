import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 10 },
        { duration: '30s', target: 25 },
        { duration: '30s', target: 50 },
        { duration: '30s', target: 0 },
    ],

    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<2000'],
    },
};

export default function () {
    const response = http.get('http://127.0.0.1:8000/');

    check(response, {
        'landing page returns 200': (r) => r.status === 200,
        'response under 2 seconds': (r) =>
            r.timings.duration < 2000,
    });

    sleep(1);
}