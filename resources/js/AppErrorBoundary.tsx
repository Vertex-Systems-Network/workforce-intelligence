import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Box, Card, CardBody, ErrorState, Text } from './design-system';
type Props = {
    children: ReactNode;
};
type State = {
    error: Error | null;
};
/** Provides app error boundary behavior for the WorkIntel client. */ export default class AppErrorBoundary extends Component<Props, State> {
    state: State = { error: null };
    /** Returns get derived state from error data required by the current workflow. */ static getDerivedStateFromError(error: Error): State {
        return { error };
    }
    /** Handles the component did catch operation for the WorkIntel client. */ componentDidCatch(error: Error, info: ErrorInfo) {
        console.error('Workforce Intelligence UI crashed during render.', error, info);
    }
    /** Renders a shared recovery state instead of a one-off dark error screen. */ render() {
        if (!this.state.error) {
            return this.props.children;
        }
        const details = this.state.error.stack || this.state.error.message;
        return <Box as="main" className="app-error-surface" minHeight="100vh" display="grid" placeItems="center" p={24} bg="var(--bg)" color="var(--text)">
          <Card elevated className="app-error-card">
            <CardBody>
              <ErrorState title="The application could not finish rendering" text="A visible recovery screen replaced the blank page. Reload once; if the error returns, include the technical details below." retry={() => window.location.reload()} retryLabel="Reload application"/>
              <Box as="details" className="app-error-details" mt={14}>
                <Box as="summary" weight={650} cursor="pointer">Technical details</Box>
                <Text as="p" color="var(--text-3)" mt={7} mb={8}>Share this stack only with an authorized administrator or support engineer.</Text>
                <Box as="pre" p={12} overflowX="auto" radius={8} bg="var(--bg)" color="var(--text-2)" whiteSpace="pre-wrap" wordBreak="break-word" size={11}>{details}</Box>
              </Box>
            </CardBody>
          </Card>
        </Box>;
    }
}
