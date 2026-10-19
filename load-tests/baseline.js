import http from 'k6/http';
import { check } from 'k6';

export const options = {
    vus: 1,
    duration: '30s',
};

export default function () {
    const response = http.get('http://127.0.0.1:8000/');

    check(response, {
        'status is 200': (r) => r.status === 200,
    });
}