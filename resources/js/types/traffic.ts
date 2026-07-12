export type ViolationType = 'illegal_parking' | 'busway_lane_intrusion';
export type ViolationStatus = 'pending_review' | 'confirmed' | 'dismissed' | 'dispatched';

export interface Coordinates {
    lng: number;
    lat: number;
}

export interface TrafficViolation {
    id: number;
    event_uuid: string;
    camera: { id: number | null; code: string | null; name: string | null };
    violation_type: ViolationType;
    plate_number: string | null;
    plate_confidence: number | null;
    plate_source: string;
    coordinates: Coordinates;
    stationary_seconds: number;
    threshold_seconds: number;
    frame_url: string | null;
    clip_url: string | null;
    status: ViolationStatus;
    detected_at: string;
    reviewed_at: string | null;
}

export interface ViolationDetectedEvent {
    id: number;
    event_uuid: string;
    violation_type: ViolationType;
    plate_number: string | null;
    plate_confidence: number | null;
    camera_id: number;
    lng: number;
    lat: number;
    stationary_seconds: number;
    frame_url: string | null;
    detected_at: string;
    status: ViolationStatus;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}
