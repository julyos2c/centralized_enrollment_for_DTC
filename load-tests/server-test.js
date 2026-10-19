import http from 'k6/http';
import { check } from 'k6';

export const options = {
    stages: [
        { duration: '10s', target: 1 },
        { duration: '10s', target: 5 },
        { duration: '10s', target: 10 },
        { duration: '10s', target: 25 },
        { duration: '10s', target: 50 },
        { duration: '10s', target: 0 },
    ],
};

export default function () {
    const response = http.get('http://127.0.0.1:8000/load-test');

    check(response, {
        'status is 200': (r) => r.status === 200,
    });
}