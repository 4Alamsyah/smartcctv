export interface CctvCamera {
    id: number;
    code: string;
    name: string;
    rtsp_url: string;
    zone_type: 'general' | 'busway_lane';
    lat: number | null;
    lng: number | null;
    lane_geofence: [number, number][] | null;
    stationary_threshold_seconds: number;
    is_active: boolean;
    last_heartbeat_at: string | null;
    created_at: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
}
